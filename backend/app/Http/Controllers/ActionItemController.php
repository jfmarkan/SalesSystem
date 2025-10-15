<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ActionItem;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

class ActionItemController extends Controller
{
    public function update(Request $request, ActionItem $action_item) // <- {action_item} en la ruta
    {
        $data = $request->validate([
            'due_date'     => ['sometimes','date_format:Y-m-d'],
            'is_completed' => ['sometimes','boolean'],
        ]);

        // Autorización: sólo dueño del plan
        $userId = Auth::id();
        $action_item->loadMissing('plan');
        if (!$action_item->plan || (int)$action_item->plan->user_id !== (int)$userId) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if (array_key_exists('due_date', $data)) {
            $action_item->due_date = $data['due_date'];
        }
        if (array_key_exists('is_completed', $data)) {
            $action_item->is_completed = (bool)$data['is_completed'];
        }
        $action_item->save();

        return response()->json([
            'id'           => $action_item->id,
            'date'         => optional($action_item->due_date)->format('Y-m-d'),
            'title'        => $action_item->title,
            'is_completed' => (bool)$action_item->is_completed,
        ]);
    }
}