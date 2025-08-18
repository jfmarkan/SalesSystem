<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserDetail;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class UserDetailController extends Controller
{
    /**
     * GET /users/{user}/details
     */
    public function show(User $user)
    {
        $detail = UserDetail::where('user_id', $user->id)->first();

        if (!$detail) {
            return response()->json(['message' => 'User details not found'], Response::HTTP_NOT_FOUND);
        }

        return response()->json($detail);
    }

    /**
     * POST /users/{user}/details  (crea o actualiza)
     * Acepta archivo 'profile_picture' (image).
     */
    public function storeOrUpdate(Request $request, User $user)
    {
        $validated = $request->validate([
            'address'         => ['nullable','string','max:255'],
            'city'            => ['nullable','string','max:255'],
            'state'           => ['nullable','string','max:255'],
            'postal_code'     => ['nullable','string','max:255'],
            'country'         => ['nullable','string','max:255'],
            'phone'           => ['nullable','string','max:255'],
            'profile_picture' => ['nullable','image','max:2048'], // ~2MB
        ]);

        $detail = UserDetail::firstOrNew(['user_id' => $user->id]);
        $detail->fill(collect($validated)->except('profile_picture')->toArray());

        if ($request->hasFile('profile_picture')) {
            // borrar anterior si existe
            if ($detail->profile_picture && Storage::disk('public')->exists($detail->profile_picture)) {
                Storage::disk('public')->delete($detail->profile_picture);
            }
            $path = $request->file('profile_picture')->store('profile_pictures', 'public');
            $detail->profile_picture = $path;
        }

        $wasNew = !$detail->exists;
        $detail->save();

        return response()->json($detail, $wasNew ? Response::HTTP_CREATED : Response::HTTP_OK);
    }

    /**
     * DELETE /users/{user}/details
     */
    public function destroy(User $user)
    {
        $detail = UserDetail::where('user_id', $user->id)->first();

        if (!$detail) {
            return response()->noContent(); // idempotente
        }

        if ($detail->profile_picture && Storage::disk('public')->exists($detail->profile_picture)) {
            Storage::disk('public')->delete($detail->profile_picture);
        }

        $detail->delete();

        return response()->noContent();
    }
}
