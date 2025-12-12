<?php

namespace App\Http\Controllers;

use App\Models\JustificationAnalysis;
use Illuminate\Http\Request;

class JustificationsAnalysisController extends Controller
{
    /**
     * GET /api/justifications-analysis
     *
     * Params:
     *  - user_id (required)
     *  - pc_code (required)
     *  - type    (optional, default 'forecast')
     */
    public function index(Request $request)
    {
        $this->authorizeIfAvailable($request, 'viewAny');

        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'pc_code' => ['required', 'integer', 'max:999'],
            'type'    => ['nullable', 'string', 'max:32'],
        ]);

        $type   = $data['type'] ?: 'forecast';
        $userId = (int)$data['user_id'];
        $pcCode = (int)$data['pc_code'];

        $rows = JustificationAnalysis::with('manager:id,first_name,last_name,username')
            ->where('user_id', $userId)
            ->where('pc_code', $pcCode)
            ->when($type, fn ($q) => $q->where('type', $type))
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        $out = $rows->map(function (JustificationAnalysis $row) {
            return [
                'id'           => $row->id,
                'user_id'      => $row->user_id,
                'manager_id'   => $row->manager_id,
                'pc_code'      => $row->pc_code,
                'type'         => $row->type,
                'year'         => $row->year,
                'month'        => $row->month,
                'note'         => $row->note,
                'manager_name' => $row->manager_name,
                'created_at'   => $row->created_at?->toIso8601String(),
            ];
        });

        return response()->json($out->values());
    }

    /**
     * POST /api/justifications-analysis
     *
     * Body:
     *  - user_id (sales rep)
     *  - pc_code
     *  - year (optional)
     *  - month (optional)
     *  - type (default 'forecast')
     *  - note (texto)
     */
    public function store(Request $request)
    {
        $manager = $request->user(); // quien está logueado

        if (!$manager) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $this->authorizeIfAvailable($request, 'create');

        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'pc_code' => ['required', 'integer', 'max:999'],
            'year'    => ['nullable', 'integer'],
            'month'   => ['nullable', 'integer', 'min:1', 'max:12'],
            'type'    => ['nullable', 'string', 'max:32'],
            'note'    => ['required', 'string'],
        ]);

        $note = new JustificationAnalysis();
        $note->user_id    = (int)$data['user_id'];     // Sales Rep
        $note->manager_id = (int)$manager->id;
        $note->pc_code    = (int)$data['pc_code'];
        $note->type       = $data['type'] ?: 'forecast';
        $note->year       = $data['year'] ?? null;
        $note->month      = $data['month'] ?? null;
        $note->note       = trim((string)$data['note']);

        $note->save();

        $note->load('manager:id,first_name,last_name,username');

        return response()->json([
            'id'           => $note->id,
            'user_id'      => $note->user_id,
            'manager_id'   => $note->manager_id,
            'pc_code'      => $note->pc_code,
            'type'         => $note->type,
            'year'         => $note->year,
            'month'        => $note->month,
            'note'         => $note->note,
            'manager_name' => $note->manager_name,
            'created_at'   => $note->created_at?->toIso8601String(),
        ], 201);
    }

    /**
     * Pequeño helper para no romper si no hay policies definidas todavía.
     */
    protected function authorizeIfAvailable(Request $request, string $ability): void
    {
        // Si más adelante definís policies para JustificationAnalysis,
        // acá podés centralizar el authorize().
        // Por ahora lo dejamos "no-op".
        return;
    }
}
