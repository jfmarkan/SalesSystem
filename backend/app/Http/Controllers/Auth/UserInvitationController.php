<?php

// app/Http/Controllers/Auth/UserInvitationController.php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\UserInvitation;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use App\Mail\UserInvitationMail;

class UserInvitationController extends Controller
{
    public function invite(Request $request)
    {
        $request->validate([
            'email' => 'required|email|unique:users,email|unique:user_invitations,email',
            'role' => 'required|in:manager,employee,golf_trainer,fitness_trainer,member,user'
        ]);

        $token = Str::random(64);

        $invitation = UserInvitation::create([
            'email' => $request->email,
            'role' => $request->role,
            'token' => $token,
            'expires_at' => now()->addHours(24),
        ]);

        Mail::to($request->email)->send(new UserInvitationMail($invitation));

        return response()->json(['message' => 'Invitación enviada.']);
    }

    public function validateToken($token)
    {
        $invitation = UserInvitation::where('token', $token)
            ->where('expires_at', '>', now())
            ->firstOrFail();

        return response()->json($invitation);
    }

    public function completeRegistration(Request $request, $token)
    {
        $invitation = UserInvitation::where('token', $token)
            ->where('expires_at', '>', now())
            ->firstOrFail();

        $validated = $request->validate([
            'first_name' => 'required',
            'last_name' => 'required',
            'gender' => 'required|in:M,F,O',
            'password' => 'required|min:8|confirmed',
            'address' => 'nullable|string|max:255'
        ]);

        $user = \App\Models\User::create([
            'email' => $invitation->email,
            'password' => bcrypt($validated['password']),
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'gender' => $validated['gender'],
            'email_verified_at' => now()
        ]);

        $user->assignRole($invitation->role);

        $user->details()->create([
            'address' => $validated['address'] ?? null,
        ]);

        $invitation->delete();

        return response()->json(['message' => 'Registro completado', 'user' => $user]);
    }
}

