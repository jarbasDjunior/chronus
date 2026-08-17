<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vehicle extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $appends = ['current_state'];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    public function getCurrentStateAttribute()
    {
        return $this->movements()->where('status', 'valid')->latest('occurred_at')->latest('id')->value('type') === 'entry' ? 'Dentro' : 'Fora';
    }

    public function people()
    {
        return $this->belongsToMany(Person::class, 'person_vehicles')
            ->withPivot('primary')
            ->withTimestamps();
    }

    public function movements()
    {
        return $this->hasMany(VehicleMovement::class);
    }

    public function setPlateAttribute($v)
    {
        $this->attributes['plate'] = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $v));
    }
}
