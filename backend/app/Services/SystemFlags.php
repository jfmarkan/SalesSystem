<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class SystemFlags
{
    private const KEY_MAINT = 'sys:maintenance';
    private const KEY_BUDGET = 'sys:budget_period_active';

    public function get(): array
    {
        return [
            'maintenance' => $this->getKey(self::KEY_MAINT, false),
            'budget_period_active' => $this->getKey(self::KEY_BUDGET, false),
        ];
    }

    public function set(array $data): array
    {
        if (array_key_exists('maintenance', $data)) {
            $this->setKey(self::KEY_MAINT, (bool)$data['maintenance']);
        }
        if (array_key_exists('budget_period_active', $data)) {
            $this->setKey(self::KEY_BUDGET, (bool)$data['budget_period_active']);
        }
        return $this->get();
    }

    private function getKey(string $key, $default = null)
    {
        $row = DB::table('cache')->where('key', $key)->first();
        if (!$row) return $default;
        $val = json_decode($row->value, true);
        return $val ?? $default;
    }

    private function setKey(string $key, $value): void
    {
        $payload = json_encode($value);
        $now = time();
        DB::table('cache')->updateOrInsert(
            ['key' => $key],
            ['value' => $payload, 'expiration' => $now + (10 * 365 * 24 * 3600)]
        );
    }
}
