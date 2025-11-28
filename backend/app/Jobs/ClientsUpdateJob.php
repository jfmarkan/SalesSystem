<?php

namespace App\Jobs;

use App\Models\ToolRun;
use App\Services\ClientsSynchronizer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ClientsUpdateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $runId;
    public array $options;

    public function __construct(int $runId, array $options = [])
    {
        $this->runId = $runId;
        $this->options = $options;
        $this->onQueue($options['queue'] ?? 'default');
    }

    public function handle(ClientsSynchronizer $sync): void
    {
        /** @var ToolRun $run */
        $run = ToolRun::findOrFail($this->runId);
        $run->status = 'running';
        $run->started_at = now();
        $run->save();

        $logger = function (string $msg) use ($run) {
            $run->refresh()->appendLog($msg);
        };

        try {
            $res = $sync->clientsUpdate($this->options, $logger);
            $run->status = 'ok';
            $run->stats = $res['summary'] ?? [];
            $run->finished_at = now();
            $run->save();
        } catch (\Throwable $e) {
            $logger('ERROR: '.$e->getMessage());
            $run->status = 'failed';
            $run->finished_at = now();
            $run->save();
            throw $e;
        }
    }
}
