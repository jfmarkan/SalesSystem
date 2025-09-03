<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserDetail;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password as PasswordRule;

class UserDetailController extends Controller
{
    /* ---------- helpers ---------- */

    /** Ensure only the authenticated user can manage own data */
    private function assertSelf(Request $request, User $user): void
    {
        $auth = $request->user();
        if (!$auth || (int) $auth->id !== (int) $user->id) {
            abort(Response::HTTP_FORBIDDEN, 'Forbidden');
        }
    }

    /** Build a public URL for a file stored on the "public" disk (no Storage::url required) */
private function pictureUrl(\Illuminate\Http\Request $request, ?string $path): ?string
{
    if (!$path) return null;
    // asegura host+puerto correctos del API (evita que el front arme mal la URL)
    $base = rtrim($request->getSchemeAndHttpHost(), '/'); // ej: http://localhost:8000
    $rel  = 'storage/' . ltrim($path, '/');               // ej: storage/profile_pictures/abc.jpg
    return $base . '/' . $rel;
}


    /* ---------- CRUD ---------- */

    /**
     * GET /api/users/{user}/details
     */
    public function show(Request $request, User $user)
    {
        $this->assertSelf($request, $user);

        $detail = UserDetail::firstWhere('user_id', $user->id);
        if (!$detail) {
            return response()->json([
                'user_id'             => $user->id,
                'address'             => null,
                'city'                => null,
                'state'               => null,
                'gender'              => null,
                'postal_code'         => null,
                'country'             => null,
                'phone'               => null,
                'profile_picture'     => null,
                'profile_picture_url' => null,
            ], Response::HTTP_OK);
        }

        $payload = $detail->toArray();
        $payload['profile_picture_url'] = $this->pictureUrl($request, $detail->profile_picture);
        return response()->json($payload);
    }

    /**
     * POST /api/users/{user}/details  (create or update)
     * Accepts 'profile_picture' (image)
     */
    public function storeOrUpdate(Request $request, User $user)
    {
        $this->assertSelf($request, $user);

        $validated = $request->validate([
            'address'         => ['nullable','string','max:255'],
            'city'            => ['nullable','string','max:255'],
            'state'           => ['nullable','string','max:255'],
            'postal_code'     => ['nullable','string','max:255'],
            'country'         => ['nullable','string','max:255'],
            'phone'           => ['nullable','string','max:255'],
            'gender'          => ['nullable','in:M,F,O'],
            'profile_picture' => ['nullable','image','max:4096'],
        ]);

        $detail = UserDetail::firstOrNew(['user_id' => $user->id]);
        $wasNew = !$detail->exists;

        // fill everything except the file
        $detail->fill(collect($validated)->except('profile_picture')->toArray());

        // handle new file upload
        if ($request->hasFile('profile_picture')) {
            // delete previous file if any
            if ($detail->profile_picture && Storage::disk('public')->exists($detail->profile_picture)) {
                Storage::disk('public')->delete($detail->profile_picture);
            }
            $path = $request->file('profile_picture')->store('profile_pictures', 'public'); // e.g. "profile_pictures/xyz.jpg"
            $detail->profile_picture = $path;
        }

        $detail->save();

        $payload = $detail->toArray();
        $payload['profile_picture_url'] = $this->pictureUrl($request, $detail->profile_picture);
        return response()->json($payload, $wasNew ? 201 : 200);
    }

    /**
     * DELETE /api/users/{user}/details
     */
    public function destroy(Request $request, User $user)
    {
        $this->assertSelf($request, $user);

        $detail = UserDetail::firstWhere('user_id', $user->id);
        if ($detail) {
            if ($detail->profile_picture && Storage::disk('public')->exists($detail->profile_picture)) {
                Storage::disk('public')->delete($detail->profile_picture);
            }
            $detail->delete();
        }
        return response()->noContent();
    }

    /**
     * PUT /api/users/{user}/password
     * Body: { current_password, password, password_confirmation }
     */
    public function updatePassword(Request $request, User $user)
    {
        $this->assertSelf($request, $user);

        $data = $request->validate([
            'current_password'      => ['required','string'],
            'password'              => ['required','string','confirmed', PasswordRule::min(8)],
            'password_confirmation' => ['required','string'],
        ]);

        if (!Hash::check($data['current_password'], $user->password)) {
            return response()->json(['message' => 'Das aktuelle Passwort ist nicht korrekt.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        if (Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Das neue Passwort darf nicht dem aktuellen entsprechen.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user->password = $data['password']; // hashed via model cast
        $user->save();

        return response()->json(['message' => 'Passwort aktualisiert'], Response::HTTP_OK);
    }
}