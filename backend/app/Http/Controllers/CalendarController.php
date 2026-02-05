<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ActionItem;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class CalendarController extends Controller
{
    public function events(Request $request)
    {
        // Validación deja que 422 se maneje solo, no lo tocamos.
        $request->validate([
            'from' => ['required','date'],
            'to'   => ['required','date','after_or_equal:from'],
        ]);

        $user = $request->user();
        $from = Carbon::parse($request->query('from'))->startOfDay();
        $to   = Carbon::parse($request->query('to'))->endOfDay();

        try {
            // 1) Acciones del usuario (due_date en rango)
            $items = ActionItem::query()
                ->whereHas('plan', fn($q) => $q->where('user_id', $user->id))
                ->whereBetween('due_date', [$from, $to])
                ->with('plan:id,user_id')
                ->get();

            $events = $items->map(function (ActionItem $it) {
                return [
                    'id'           => $it->id,
                    'date'         => optional($it->due_date)->toDateString(),
                    'type'         => 'action',
                    'title'        => $it->title,
                    'is_completed' => $it->is_completed,
                ];
            });

            // 2) Indicadores fijos del mes de "from"
            $indicators = $this->monthlyIndicators($from->copy());

            $indicators = collect($indicators)->filter(function ($e) use ($from, $to) {
                $d = Carbon::parse($e['date']);
                return $d->between($from, $to);
            });

            // FIX: usamos Support\Collection para mergear ARRAYS, no Eloquent\Collection
            $payload = collect()
                ->merge($events->values()->all())
                ->merge($indicators->values()->all())
                ->values();

            return response()->json($payload);
        } catch (\Throwable $e) {
            Log::error('Calendar events error', [
                'exception' => get_class($e),
                'error'     => $e->getMessage(),
                'user_id'   => $user?->id,
                'from'      => $from->toDateTimeString(),
                'to'        => $to->toDateTimeString(),
                'file'      => $e->getFile().':'.$e->getLine(),
                'trace'     => substr($e->getTraceAsString(), 0, 4000),
                'request'   => $request->all(),
            ]);

            $payload = [
                'message' => 'Kalender konnte nicht geladen werden.',
            ];

            if (config('app.debug')) {
                $payload['error']     = $e->getMessage();
                $payload['exception'] = get_class($e);
                $payload['file']      = $e->getFile().':'.$e->getLine();
            }

            return response()->json($payload, 500);
        }
    }

    private function monthlyIndicators(Carbon $anyDayOfMonth): array
    {
        $y = (int)$anyDayOfMonth->format('Y');
        $m = (int)$anyDayOfMonth->format('m');

        $detection   = Carbon::create($y, $m, 4, 0, 0, 0);
        $repoting    = Carbon::create($y, $m, 10, 0, 0, 0);
        $forecasting = Carbon::create($y, $m, 15, 0, 0, 0);
        $controlling = $forecasting->copy()->next(Carbon::FRIDAY);

        return [
            [
                'id'    => "ind-{$detection->toDateString()}-detection",
                'date'  => $detection->toDateString(),
                'type'  => 'detection',
                'title' => 'Abweichungsanalyse',
            ],
            [
                'id'    => "ind-{$repoting->toDateString()}-repoting",
                'date'  => $repoting->toDateString(),
                'type'  => 'repoting',
                'title' => 'Reporting',
            ],
            [
                'id'    => "ind-{$forecasting->toDateString()}-forecasting",
                'date'  => $forecasting->toDateString(),
                'type'  => 'forecasting',
                'title' => 'Forecasting',
            ],
            [
                'id'    => "ind-{$controlling->toDateString()}-interview",
                'date'  => $controlling->toDateString(),
                'type'  => 'interview',
                'title' => 'Controlling Gespräch',
            ],
        ];
    }
}
