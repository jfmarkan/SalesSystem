<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

use App\Http\Controllers\Controller;

use App\Models\Company;
use App\Models\Team;
use App\Models\User;
use App\Models\UserDetail;
use App\Models\TeamMember;

class OrganizationController extends Controller
{
    /**
     * Returns the full organization chart tree
     * in a format compatible with PrimeVue OrganizationChart.
     */
    public function orgChart()
    {
        $roots = Company::with([
                'children',
                'teams.managerUser',
                'teams.members',
            ])
            ->whereNull('parent_company_id')
            ->get();

        if ($roots->count() === 1) {
            $rootNode = $this->buildCompanyNode($roots->first());
        } else {
            $children = $roots
                ->map(fn(Company $company) => $this->buildCompanyNode($company))
                ->values()
                ->all();

            $rootNode = [
                'key'      => '0',
                'type'     => 'root',
                'label'    => 'Organisation',
                'data'     => [
                    'name'  => 'Organisation',
                    'title' => 'Wurzel',
                ],
                'children' => $children,
            ];
        }

        return response()->json($rootNode);
    }

    /**
     * Build a node for a company (head or subsidiary) including its children.
     */
    private function buildCompanyNode(Company $company): array
    {
        $isHead = $company->parent_company_id === null;
        $kind   = $isHead ? 'company' : 'subsidiary';

        $node = [
            'key'   => 'company-' . $company->id,
            'type'  => 'unit',
            'label' => $company->name,
            'data'  => [
                'id'          => $company->id,
                'name'        => $company->name,
                'title'       => $isHead ? 'Stammgesellschaft' : 'Tochtergesellschaft',
                'kind'        => $kind,
                'entity'      => 'company',
                'canDelete'   => !$company->children()->exists() && !$company->teams()->exists(),
                'parent_id'   => $company->parent_company_id,
            ],
            'children' => [],
        ];

        // Child companies
        foreach ($company->children as $child) {
            $node['children'][] = $this->buildCompanyNode($child);
        }

        // Teams under this company
        foreach ($company->teams as $team) {
            $node['children'][] = $this->buildTeamNode($team);
        }

        return $node;
    }

    /**
     * Build a node for a team, including manager and members as person nodes.
     */
    private function buildTeamNode(Team $team): array
    {
        // Manager info to render in the team node
        $managerData = null;

        if ($team->managerUser) {
            $managerFullName = trim(
                ($team->managerUser->first_name ?? '') . ' ' . ($team->managerUser->last_name ?? '')
            );

            $managerData = [
                'id'    => $team->managerUser->id,
                'name'  => $managerFullName,
                'email' => $team->managerUser->email,
                'image' => $this->userAvatarUrl($team->managerUser),
            ];
        }

        // Base team node: manager is rendered in this node (NOT as child node)
        $teamNode = [
            'key'   => 'team-' . $team->id,
            'type'  => 'unit',
            'label' => $team->name,
            'data'  => [
                'id'         => $team->id,
                // big text on card = team name (will be styled by Vue)
                'name'       => $team->name,
                // small text under it = manager name (or empty if no manager)
                'title'      => $managerData['name'] ?? '',
                'kind'       => 'team',
                'entity'     => 'team',
                'company_id' => $team->company_id,
                'canDelete'  => !$team->members()->exists(),
                'manager'    => $managerData, // includes 'image'
            ],
            'children' => [],
        ];

        // Members excluding manager (they will be listed in the "Verkäufer" node)
        $members = [];
        $managerId = $team->manager_user_id ? (int) $team->manager_user_id : null;

        foreach ($team->members as $member) {
            // skip manager; manager is already on the team card
            if ($managerId && $member->id === $managerId) {
                continue;
            }

            $fullName = trim(
                ($member->first_name ?? '') . ' ' . ($member->last_name ?? '')
            );

            $members[] = [
                'id'    => $member->id,
                'name'  => $fullName,
                'email' => $member->email,
                'role'  => $member->pivot->role ?? null,           // SALES_REP, KAM etc.
                'image' => $this->userAvatarUrl($member),          // ✅ key for AvatarGroup
            ];
        }

        // Single child node that visually groups all sellers
        $membersNode = [
            'key'   => 'team-' . $team->id . '-members',
            'type'  => 'members', // PrimeVue slot: #members
            'label' => 'Verkäufer',
            'data'  => [
                'kind'     => 'members_group',
                'entity'   => 'team_members',
                'team_id'  => $team->id,
                'members'  => $members,          // used for avatar group + PickList
                'count'    => count($members),
            ],
            'children' => [],
        ];

        $teamNode['children'] = [$membersNode];

        return $teamNode;
    }

    /**
     * Build absolute avatar URL from user_details.profile_picture
     * using Laravel storage symlink (public/storage/...).
     */
    private function userAvatarUrl(?User $user): ?string
    {
        if (!$user) {
            return null;
        }

        // Try relation if it exists (detail or details) to avoid extra query
        $detail = null;

        if (isset($user->detail)) {
            $detail = $user->detail;
        } elseif (isset($user->details)) {
            $detail = $user->details;
        }

        // Fallback: direct lookup by user_id
        if (!$detail instanceof UserDetail) {
            $detail = UserDetail::where('user_id', $user->id)->first();
        }

        if (!$detail || !$detail->profile_picture) {
            return null;
        }

        // Normalize stored path
        // Cases:
        //  1) "john.png"
        //  2) "profile_pictures/john.png"
        //  3) "/profile_pictures/john.png"
        $path = ltrim($detail->profile_picture, '/');

        if (Str::startsWith($path, 'profile_pictures/')) {
            // DB already stores "profile_pictures/xxx"
            return asset('storage/' . $path);
        }

        // Otherwise assume it's just the filename: "john.png"
        return asset('storage/profile_pictures/' . $path);
    }


    /* ---------------- CREATE ---------------- */

    /**
     * Create a company (head or subsidiary).
     */
    public function storeCompany(Request $request)
    {
        $data = $request->validate([
            'name'              => ['required', 'string', 'max:120'],
            'parent_company_id' => ['nullable', 'integer', 'exists:companies,id'],
        ]);

        $company = Company::create([
            'name'              => $data['name'],
            'parent_company_id' => $data['parent_company_id'] ?? null,
        ]);

        return response()->json($company, 201);
    }

    /**
     * Create a team under a company and assign a manager.
     */
    public function storeTeam(Request $request)
    {
        $data = $request->validate([
            'name'            => ['required', 'string', 'max:80'],
            'company_id'      => ['required', 'integer', 'exists:companies,id'],
            'manager_user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $manager = User::findOrFail($data['manager_user_id']);

        // optional: ensure manager is not already manager of another team
        $alreadyManager = Team::where('manager_user_id', $manager->id)
            ->whereNull('deleted_at')
            ->exists();

        if ($alreadyManager) {
            return response()->json([
                'message' => 'Dieser Benutzer ist bereits Manager eines anderen Teams.'
            ], 422);
        }

        $team = Team::create([
            'name'            => $data['name'],
            'company_id'      => $data['company_id'],
            'manager_user_id' => $manager->id,
        ]);

        return response()->json($team, 201);
    }

    /**
     * Add an existing user to a team (team_members table).
     */
    public function addTeamMember(Request $request)
    {
        $data = $request->validate([
            'team_id' => ['required', 'integer', 'exists:teams,id'],
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'role'    => ['required', 'in:MANAGER,SALES_REP,KAM'],
        ]);

        $exists = TeamMember::where('team_id', $data['team_id'])
            ->where('user_id', $data['user_id'])
            ->whereNull('deleted_at')
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'Dieser Benutzer ist bereits im Team.'], 422);
        }

        $member = TeamMember::create($data);

        return response()->json($member, 201);
    }

    /* ---------------- UPDATE ---------------- */

    /**
     * Update company basic data (name).
     */
    public function updateCompany(Request $request, Company $company)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        $company->update(['name' => $data['name']]);

        return response()->json($company);
    }

    /**
     * Update team basic data (name).
     */
    public function updateTeam(Request $request, Team $team)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
        ]);

        $team->update(['name' => $data['name']]);

        return response()->json($team);
    }

    /* ---------------- DELETE ---------------- */

    /**
     * Delete a company if it has no children or teams.
     */
    public function deleteCompany(Company $company)
    {
        if ($company->children()->exists() || $company->teams()->exists()) {
            return response()->json(['message' => 'Die Firma kann nicht gelöscht werden, da noch Abhängigkeiten bestehen.'], 422);
        }

        $company->delete();

        return response()->json(['message' => 'Firma erfolgreich gelöscht.']);
    }

    /**
     * Delete a team if it has no members.
     */
    public function deleteTeam(Team $team)
    {
        if ($team->members()->exists()) {
            return response()->json(['message' => 'Das Team kann nicht gelöscht werden, da es noch Mitglieder hat.'], 422);
        }

        $team->delete();

        return response()->json(['message' => 'Team erfolgreich gelöscht.']);
    }

    /**
     * Remove a team member (soft delete in team_members).
     */
    public function removeTeamMember(Request $request)
    {
        $data = $request->validate([
            'team_id' => ['required', 'integer', 'exists:teams,id'],
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        DB::table('team_members')
            ->where('team_id', $data['team_id'])
            ->where('user_id', $data['user_id'])
            ->update([
                'deleted_at' => now(),
                'updated_at' => now(),
            ]);

        return response()->json(['message' => 'Mitglied entfernt.']);
    }


    /**
     * Sync team members for a given team using PickList selection.
     * Existing members are replaced by the given list (manager excluded).
     */
    public function syncTeamMembers(Request $request)
    {
        $data = $request->validate([
            'team_id'           => ['required', 'integer', 'exists:teams,id'],
            'members'           => ['array'],
            'members.*.user_id' => ['required', 'integer', 'exists:users,id'],
            'members.*.role'    => ['nullable', 'string', 'in:MANAGER,SALES_REP,KAM'],
        ]);

        $teamId   = (int) $data['team_id'];
        $members  = collect($data['members'] ?? []);

        // Normalize incoming user IDs
        $desiredIds = $members
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        DB::transaction(function () use ($teamId, $members, $desiredIds) {

            // Load all existing members for this team (including soft-deleted)
            $existing = DB::table('team_members')
                ->where('team_id', $teamId)
                ->get()
                ->keyBy('user_id');

            $now = now();

            // Upsert each desired member
            foreach ($members as $m) {
                $userId = (int) $m['user_id'];
                $role   = $m['role'] ?? 'SALES_REP';

                $tm = $existing->get($userId);

                if ($tm) {
                    // Already exists → update role and "restore" (deleted_at = null)
                    DB::table('team_members')
                        ->where('team_id', $teamId)
                        ->where('user_id', $userId)
                        ->update([
                            'role'       => $role,
                            'deleted_at' => null,
                            'updated_at' => $now,
                        ]);
                } else {
                    // New combination → insert
                    DB::table('team_members')->insert([
                        'team_id'    => $teamId,
                        'user_id'    => $userId,
                        'role'       => $role,
                        'created_at' => $now,
                        'updated_at' => $now,
                        'deleted_at' => null,
                    ]);
                }
            }

            // Soft-delete members that are no longer in the desired list
            foreach ($existing as $userId => $tm) {
                if (!$desiredIds->contains((int) $userId)) {
                    DB::table('team_members')
                        ->where('team_id', $teamId)
                        ->where('user_id', (int) $userId)
                        ->update([
                            'deleted_at' => $now,
                            'updated_at' => $now,
                        ]);
                }
            }
        });

        return response()->json([
            'message' => 'Die Team-Mitglieder wurden erfolgreich synchronisiert.',
        ]);
    }

    /* ---------------- LISTS FOR FRONT ---------------- */

    /**
     * Returns admin users for manager selection in the frontend.
     */
    public function adminUsers()
    {
        // Manager candidates: role_id = 3 or Spatie role 'manager'
        $users = User::query()
            ->select('id', 'first_name', 'last_name', 'email', 'role_id')
            ->where(function ($q) {
                $q->where('role_id', 3)
                ->orWhereHas('roles', function ($qr) {
                    $qr->where('name', 'manager');
                });
            })
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get()
            ->unique('id')
            ->values();

        // Map + resolve avatar via user_details.profile_picture
        return $users->map(function ($u) {
            return [
                'id'    => $u->id,
                'name'  => trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? '')),
                'email' => $u->email,
                'image' => $this->userAvatarUrl($u), // ✅ uses user_details
            ];
        });
    }

    public function users()
    {
        $users = User::select('id', 'first_name', 'last_name', 'email')
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();

        return $users->map(function ($u) {
            return [
                'id'    => $u->id,
                'name'  => trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? '')),
                'email' => $u->email,
                'image' => $this->userAvatarUrl($u), // ✅ also here, same helper
            ];
        });
    }

}
