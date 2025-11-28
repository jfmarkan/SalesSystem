<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ToolRun extends Model
{
    protected $fillable = [
        'tool','status','user_id','options','stats','log','started_at','finished_at',
    ];
    protected $casts = [
        'options' => 'array',
        'stats' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function appendLog(string $line): void
    {
        $ts = now()->format('Y-m-d H:i:s');
        $this->log = rtrim((string)$this->log)."\n[$ts] ".$line;
        $this->save();
    }
}
