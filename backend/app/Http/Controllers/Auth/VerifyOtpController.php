<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Carbon\Carbon;

class VerifyOtpController extends Controller
{
    public function verify(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'otp' => 'required|digits:6',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['message' => 'Usuario no encontrado.'], 404);
        }

        if ($user->email_verified_at) {
            return response()->json(['message' => 'El email ya fue verificado.'], 400);
        }

        if (!$user->otp || $user->otp !== $request->otp) {
            return response()->json(['message' => 'Código OTP incorrecto.'], 400);
        }

        if (Carbon::now()->gt($user->otp_expires_at)) {
            return response()->json(['message' => 'El código OTP ha expirado.'], 400);
        }

        // Verificar al usuario
        $user->email_verified_at = now();
        $user->otp = null;
        $user->otp_expires_at = null;
        $user->save();

        // Crear token de autenticación Sanctum
        Auth::login($user);
        $request->session()->regenerate();

        return response()->json([
            'message' => 'Verificación exitosa.',
            'user' => $user->load('roles'),
            'roles' => $user->roles->pluck('name'),
        ]);
    }
}
