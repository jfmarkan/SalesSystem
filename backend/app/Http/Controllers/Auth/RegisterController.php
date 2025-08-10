<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Carbon\Carbon;

class RegisterController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'gender' => 'required|in:M,F,X',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:6|confirmed',
        ]);

        $otp = rand(100000, 999999); // Genera OTP de 6 dígitos


        $user = User::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'gender' => $request->gender,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $user->otp = $otp;
        $user->otp_expires_at = now()->addMinutes(10);
        $user->save();


        // Enviar OTP por email
        Mail::to($user->email)->send(new \App\Mail\SendOtpMail($user));

        return response()->json([
            'message' => 'Registro exitoso. Revisa tu email para validar la cuenta.',
        ], 201);
    }
}