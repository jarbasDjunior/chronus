<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VehicleMovement extends Model
{
    protected $guarded = [];

    protected $casts = ['occurred_at' => 'datetime', 'synced_at' => 'datetime'];

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function person()
    {
        return $this->belongsTo(Person::class);
    }

    public function location()
    {
        return $this->belongsTo(AccessLocation::class, 'access_location_id');
    }

    public function operator()
    {
        return $this->belongsTo(User::class, 'operator_id');
    }
}
