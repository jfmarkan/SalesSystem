<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\Assignment;
use App\Models\ClientProfitCenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role as SpatieRole;
use Spatie\Activitylog\Models\Activity;

class UserAdministrationController extends Controller
{
    public function index(){
        $list = User::query()->get()->map(function(User $u){
            return [
                'id' => (int)$u->id,
                'first_name' => $u->first_name,
                'last_name' => $u->last_name,
                'name' => trim(($u->first_name ?? '').' '.($u->last_name ?? '')),
                'email' => $u->email,
                'disabled' => (bool)($u->disabled ?? false),
                'roles' => $u->getRoleNames()->values(),
                'teamIds' => TeamMember::where('user_id',$u->id)->pluck('team_id')->values(),
                'online' => $u->online ?? null,          // opcional si lo tienes
                'last_seen' => $u->last_seen ?? null,    // opcional
            ];
        });
        return response()->json($list);
    }

    public function roles(){
        return response()->json(SpatieRole::orderBy('name')->pluck('name'));
    }

    public function teams(){
        return response()->json(Team::select('id','name')->orderBy('name')->get());
    }

    public function clients(Request $request){
    $request->validate(['userId'=>['required','integer','exists:users,id']]);
    $userId = (int)$request->query('userId');

    // Trae asignaciones del usuario con CPC -> Client (FK correcta por client_group_number)
    $rows = Assignment::where('user_id', $userId)
        ->with([
            'clientProfitCenter:id,client_group_number',
            'clientProfitCenter.client:client_group_number,client_name',
        ])->get();

    // Agrupa por cliente (client_group_number), suma todos sus CPC/assignments
    $grouped = $rows->groupBy(fn($a) => $a->clientProfitCenter?->client?->client_group_number);

    $out = $grouped->map(function ($group, $cgn) {
        $first = $group->first();
        return [
            'clientGroupNumber' => (int)$cgn,
            'clientName'        => $first?->clientProfitCenter?->client?->client_name ?? "Kunde {$cgn}",
            'assignmentIds'     => $group->pluck('id')->values()->map(fn($x)=>(int)$x),
            'cpcIds'            => $group->pluck('client_profit_center_id')->unique()->values()->map(fn($x)=>(int)$x),
            'count'             => $group->count(),
        ];
    })->values();

    return response()->json($out);
    }

    public function transfer($id, Request $request){
        $data = $request->validate([
            'toUserId'            => ['required','integer','exists:users,id'],
            'clientGroupNumbers'  => ['array'],   // preferido: mueve el cliente entero
            'assignmentIds'       => ['array'],   // compat: si viene por asignaciones
            'toTeamId'            => ['nullable','integer'], // opcional: set team_id
        ]);

        $fromUser = (int)$id;
        $toUser   = (int)$data['toUserId'];
        $toTeamId = $data['toTeamId'] ?? null;

        // Determina asignaciones a mover
        $q = Assignment::where('user_id', $fromUser);

        if (!empty($data['clientGroupNumbers'])) {
            $cgns = collect($data['clientGroupNumbers'])->map(fn($n)=>(int)$n)->unique()->values();
            $cpcIds = ClientProfitCenter::whereIn('client_group_number', $cgns)->pluck('id');
            $q->whereIn('client_profit_center_id', $cpcIds);
        } elseif (!empty($data['assignmentIds'])) {
            $ids = collect($data['assignmentIds'])->map(fn($n)=>(int)$n)->unique()->values();
            $q->whereIn('id', $ids);
        }

        $moved = 0;
        DB::transaction(function() use ($q, $toUser, $toTeamId, &$moved){
            $ids = $q->pluck('id');
            $moved = $ids->count();
            if ($moved) {
                $payload = ['user_id' => $toUser];
                if ($toTeamId) $payload['team_id'] = (int)$toTeamId;
                Assignment::whereIn('id', $ids)->update($payload);
            }
        });

        return response()->json(['transferred' => $moved]);
    }

    public function block($id, \Illuminate\Http\Request $request){
        $data = $request->validate(['disabled' => ['required','boolean']]);
        $user = \App\Models\User::findOrFail($id);

        $user->disabled = $data['disabled'];
        $user->save();

        // revoke all tokens when disabling
        if ($user->disabled && method_exists($user, 'tokens')) {
            $user->tokens()->delete();
        }

        activity('user')->performedOn($user)
            ->withProperties(['disabled' => $user->disabled])
            ->log($user->disabled ? 'BLOCK' : 'UNBLOCK');

        // payload consistente con el listado
        return response()->json([
            'id'       => (int)$user->id,
            'first_name' => $user->first_name,
            'last_name'  => $user->last_name,
            'name'       => trim(($user->first_name ?? '').' '.($user->last_name ?? '')),
            'email'      => $user->email,
            'disabled'   => (bool)$user->disabled,
            'roles'      => $user->getRoleNames()->values(),
            'teamIds'    => \App\Models\TeamMember::where('user_id',$user->id)->pluck('team_id')->values(),
        ]);
    }

    public function updateRole($id, Request $request){
        $request->validate(['role'=>['required','string']]);
        $role = SpatieRole::where('name', $request->input('role'))->firstOrFail();

        $user = User::findOrFail($id);
        $user->syncRoles([$role->name]);

        activity('user')->performedOn($user)->withProperties(['role'=>$role->name])->log('CHANGE_ROLE');

        return response()->json($this->serialize($user));
    }

    public function updateTeams(Request $request, int $id){
        $data = $request->validate([
            'teamIds'   => ['required','array','min:1'],
            'teamIds.*' => ['integer','exists:teams,id'],
        ]);

        $teamIds   = array_values(array_unique($data['teamIds']));
        $firstTeam = $teamIds[0];

        /** @var User $user */
        $user = User::findOrFail($id);

        // 👇 EXACT value required by your DB: "SALES-REP" (uppercase + hyphen)
        $ROLE_VALUE = 'SALES_REP';

        DB::transaction(function () use ($user, $teamIds, $firstTeam, $ROLE_VALUE) {
            // restore/create selected memberships with correct role,
            // soft-delete the rest (respects SoftDeletes)
            $current = TeamMember::withTrashed()
                ->where('user_id', $user->id)
                ->get()
                ->keyBy('team_id');

            foreach ($teamIds as $tid) {
                if (isset($current[$tid])) {
                    $tm = $current[$tid];
                    if ($tm->trashed()) {
                        $tm->restore();
                    }
                    // ensure role value is exactly what your table uses
                    if ($tm->role !== $ROLE_VALUE) {
                        $tm->role = $ROLE_VALUE;
                        $tm->save();
                    }
                } else {
                    TeamMember::create([
                        'team_id' => $tid,
                        'user_id' => $user->id,
                        'role'    => $ROLE_VALUE,
                    ]);
                }
            }

            // soft-delete memberships NOT in the new set
            TeamMember::where('user_id', $user->id)
                ->whereNotIn('team_id', $teamIds)
                ->delete();

            // move ALL assignments of this user to the first selected team
            Assignment::where('user_id', $user->id)
                ->update(['team_id' => $firstTeam]);
        });

        return response()->json([
            'userId'  => (int)$user->id,
            'teamIds' => $teamIds,
            'message' => 'Teams erfolgreich aktualisiert.',
        ]);
    }

    public function logs($id){
        $user = User::findOrFail($id);
        $entries = Activity::where('log_name','user')->where('subject_type',User::class)->where('subject_id',$user->id)
            ->orderByDesc('created_at')->limit(200)->get()
            ->map(fn($a)=>['id'=>$a->id,'ts'=>$a->created_at->toIso8601String(),'action'=>$a->description,'meta'=>$a->properties]);
        return response()->json($entries);
    }

    protected function serialize(User $u): array{
        return [
            'id'=>(int)$u->id,
            'first_name'=>$u->first_name,
            'last_name'=>$u->last_name,
            'name'=>trim(($u->first_name??'').' '.($u->last_name??'')),
            'email'=>$u->email,
            'disabled'=>(bool)($u->disabled ?? false),
            'roles'=>$u->getRoleNames()->values(),
            'teamIds'=>TeamMember::where('user_id',$u->id)->pluck('team_id')->values(),
        ];
    }
}