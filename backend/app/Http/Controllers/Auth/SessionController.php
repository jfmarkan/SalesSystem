<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SessionController extends Controller
{
    /**
     * Handle SPA login (session + Sanctum stateful) with optional OTP flow.
     */
    public function login(Request $request)
{
    try {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return response()->json(['message' => 'Ungültige Anmeldedaten'], 422);
        }

        // Regenerate session to prevent fixation
        $request->session()->regenerate();

        /** @var \App\Models\User $user */
        $user = $request->user()->fresh();

        // ⛔ Blocked: reject login, clear session & tokens
        if (!empty($user->disabled)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            if (method_exists($user, 'tokens')) {
                $user->tokens()->delete();
            }
            return response()->json([
                'code'    => 'USER_BLOCKED',
                'message' => 'Ihr Benutzer wurde gesperrt. Wenn Sie glauben, dass dies ein Fehler ist, wenden Sie sich bitte an den Systemadministrator.'
            ], 403);
        }

        // OTP flow (si aplica)
        if (is_null($user->email_verified_at)) {
            if (empty($user->otp) || empty($user->otp_expires_at) || \Carbon\Carbon::parse($user->otp_expires_at)->isPast()) {
                $user->otp = random_int(100000, 999999);
                $user->otp_expires_at = now()->addMinutes(10);
                $user->save();
            }
            return response()->json([
                'verify'  => true,
                'message' => 'Dein Konto ist noch nicht verifiziert. Prüfe deine E-Mails und gib den Code ein.',
                'email'   => $user->email,
            ]);
        }

        // Roles seguros
        $roles = [];
        if (method_exists($user, 'roles')) {
            $user->load('roles');
            $roles = $user->roles->pluck('name')->values();
        }
        $user->load('details');

        return response()->json([
            'message' => 'Anmeldung erfolgreich',
            'user'    => [
                'id'         => $user->id,
                'first_name' => $user->first_name,
                'last_name'  => $user->last_name,
                'email'      => $user->email,
                'role_id'    => $user->role_id,
                'gender'     => optional($user->details)->gender,
                'disabled'   => (bool)($user->disabled ?? false),
            ],
            'roles'   => $roles,
            'verify'  => false,
            'email'   => $user->email,
        ]);
    } catch (\Illuminate\Validation\ValidationException $e) {
        throw $e;
    } catch (\Throwable $e) {
        Log::error('Login error', ['message'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()]);
        return response()->json(['message' => 'Serverfehler beim Anmelden'], 500);
    }
}


    /**
     * Logout current user (session-based).
     */
    public function logout(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Sitzung beendet']);
    }
}