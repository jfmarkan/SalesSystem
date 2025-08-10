<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Mail\SendOtpMail;
use Illuminate\Support\Facades\Mail;

class OtpController extends Controller
{
    public function resend(Request $request)
    {
        $request->validate([
            'email' => 'required|email'
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['message' => 'Usuario no encontrado.'], 404);
        }

        if (!is_null($user->email_verified_at)) {
            return response()->json(['message' => 'Esta cuenta ya fue verificada.'], 400);
        }

        $user->otp = random_int(100000, 999999);
        $user->otp_expires_at = now()->addHours(2);
        $user->save();

        Mail::to($user->email)->send(new SendOtpMail($user));

        return response()->json(['message' => 'Nuevo código enviado correctamente.']);
    }
}
