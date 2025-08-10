<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use App\Mail\SendOtpMail;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class SessionController extends Controller
{
    public function login(Request $request)
{
    $credentials = $request->validate([
        'email' => ['required', 'email'],
        'password' => ['required'],
    ]);

    // Intentamos autenticar y mantener la sesión
    if (!Auth::attempt($credentials)) {
        throw ValidationException::withMessages([
            'email' => ['Credenciales inválidas.'],
        ]);
    }

    $request->session()->regenerate();

    /** @var \App\Models\User $user */
    $user = Auth::user();

    if (is_null($user->email_verified_at)) {
        // OTP generado solo si no hay o está vencido
        if (is_null($user->otp) || Carbon::parse($user->otp_expires_at)->isPast()) {
            $user->otp = random_int(100000, 999999);
            $user->otp_expires_at = now()->addMinutes(10);
            $user->save(); // ✅ Esto no dará error si usamos el modelo Authenticated
        }

        // ⚠️ No se retorna token, sólo el estado de verificación
        return response()->json([
            'verify' => true,
            'message' => 'Tu cuenta aún no está verificada.',
            'email' => $user->email,
        ]);
    }

    // ✅ Retornamos user con roles correctamente
    return response()->json([
        'message' => 'Login exitoso',
        'user' => $user->load('roles'),
        'roles' => $user->roles->pluck('name'),
    ]);
}

    public function logout(Request $request)
    {
        // Esto es para sesión vía cookies, no tokens
        auth()->guard('web')->logout(); 
    
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    
        return response()->json(['message' => 'Sesión cerrada']);
    }
}
