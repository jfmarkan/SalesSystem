<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use App\Models\UserDetail;
use Carbon\Carbon;

class SessionController extends Controller
{
    /**
     * Helper para generar la URL pública de la foto de perfil
     */
    private function pictureUrl(Request $request, ?string $path): ?string
    {
        if (!$path) return null;
        $base = rtrim($request->getSchemeAndHttpHost(), '/'); // ej: http://localhost:8000
        $rel  = 'storage/' . ltrim($path, '/');               // ej: storage/profile_pictures/abc.jpg
        return $base . '/' . $rel;
    }

    /**
     * Handle SPA login (session + Sanctum stateful) con OTP opcional.
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

            // Regenerar sesión para prevenir fixation
            $request->session()->regenerate();

            /** @var \App\Models\User $user */
            $user = $request->user()->fresh();

            // ⛔ Usuario bloqueado
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

            // OTP Flow
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

            // Cargar detalles del usuario (perfil)
            $user->load('details');
            $detail = $user->details;
            $pictureUrl = $this->pictureUrl($request, optional($detail)->profile_picture);

            // Respuesta final
            return response()->json([
                'message' => 'Anmeldung erfolgreich',
                'user'    => [
                    'id'         => $user->id,
                    'first_name' => $user->first_name,
                    'last_name'  => $user->last_name,
                    'email'      => $user->email,
                    'role_id'    => $user->role_id,
                    'gender'     => optional($detail)->gender,
                    'disabled'   => (bool)($user->disabled ?? false),
                    'user_details' => [
                        'profile_picture'     => optional($detail)->profile_picture,
                        'profile_picture_url' => $pictureUrl,
                        'phone'               => optional($detail)->phone,
                        'address'             => optional($detail)->address,
                        'city'                => optional($detail)->city,
                        'country'             => optional($detail)->country,
                    ],
                ],
                'roles'   => $roles,
                'verify'  => false,
                'email'   => $user->email,
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Login error', [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ]);
            return response()->json(['message' => 'Serverfehler beim Anmelden'], 500);
        }
    }

    /**
     * Logout actual (basado en sesión).
     */
    public function logout(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Sitzung beendet']);
    }
}
