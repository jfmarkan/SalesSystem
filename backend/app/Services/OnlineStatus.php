<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class OnlineStatus
{
    /** user online if session last_activity <= 300s or PAT last_used_at <= 300s */
    public function mapUsersOnline(array $users): array
    {
        $ids = array_column($users, 'id');
        $now = time();
        $since = $now - 300;

        $sess = DB::table('sessions')
            ->whereIn('user_id', $ids)
            ->where('last_activity', '>=', $since)
            ->pluck('last_activity', 'user_id');

        $tokens = DB::table('personal_access_tokens')
            ->whereIn('tokenable_id', $ids)
            ->whereNotNull('last_used_at')
            ->pluck('last_used_at', 'tokenable_id');

        foreach ($users as &$u) {
            $u['online'] = false;
            if (isset($sess[$u['id']])) $u['online'] = true;
            if (isset($tokens[$u['id']]) && strtotime($tokens[$u['id']]) >= ($now - 300)) $u['online'] = true;
        }
        return $users;
    }

    public function kickUser(int $userId): void
    {
        DB::table('sessions')->where('user_id', $userId)->delete();
        DB::table('personal_access_tokens')->where('tokenable_id', $userId)->delete();
    }
}
