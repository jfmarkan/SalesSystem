<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ActionItem;
use Carbon\Carbon;


class CalendarController extends Controller
{
    public function events(Request $request)
    {
        $request->validate([
            'from' => ['required','date'],
            'to'   => ['required','date','after_or_equal:from'],
        ]);

        $user = $request->user();
        $from = Carbon::parse($request->query('from'))->startOfDay();
        $to   = Carbon::parse($request->query('to'))->endOfDay();

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
        // Filtrar para que solo devuelva dentro del rango solicitado
        $indicators = collect($indicators)->filter(function ($e) use ($from, $to) {
            $d = Carbon::parse($e['date']);
            return $d->between($from, $to);
        });

        // Unir y responder
        return $events->merge($indicators)->values();
    }

    private function monthlyIndicators(Carbon $anyDayOfMonth): array
    {
        $y = (int)$anyDayOfMonth->format('Y');
        $m = (int)$anyDayOfMonth->format('m');

        $detection   = Carbon::create($y, $m, 4, 0, 0, 0);
        $repoting    = Carbon::create($y, $m, 10, 0, 0, 0);
        $forecasting = Carbon::create($y, $m, 15, 0, 0, 0);
        // Viernes siguiente al forecasting (estrictamente el próximo viernes)
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
