<?php

namespace App\Jobs;

use App\Services\ClientsSynchronizer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ClientsUpdateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public array $options;

    public function __construct(array $options = [])
    {
        $this->options = $options;
        $this->onQueue($options['queue'] ?? 'default');
    }

    public function handle(ClientsSynchronizer $sync): void
    {
        $result = $sync->clientsUpdate($this->options);
        Log::info('ClientsUpdateJob finished', $result);
    }
}
