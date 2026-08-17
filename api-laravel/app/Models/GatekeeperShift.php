<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class GatekeeperShift extends Model
{
    protected $guarded = [];

    protected $appends = ['status'];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'break_started_at' => 'datetime',
            'break_ended_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    protected function status(): Attribute
    {
        return Attribute::get(fn () => match (true) {
            $this->ended_at !== null => 'finished',
            $this->break_started_at !== null && $this->break_ended_at === null => 'on_break',
            default => 'working',
        });
    }

    public function gatekeeper()
    {
        return $this->belongsTo(Gatekeeper::class);
    }

    public function location()
    {
        return $this->belongsTo(AccessLocation::class, 'access_location_id');
    }
}
