<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Person extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $hidden = ['cpf'];

    protected $appends = ['current_state'];

    public function getCurrentStateAttribute()
    {
        return $this->movements()->where('status', 'valid')->latest('occurred_at')->latest('id')->value('type') === 'entry' ? 'Dentro' : 'Fora';
    }

    public function category()
    {
        return $this->belongsTo(PersonCategory::class, 'category_id');
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function vehicles()
    {
        return $this->belongsToMany(Vehicle::class, 'person_vehicles')
            ->withPivot('primary')
            ->withTimestamps();
    }

    public function movements()
    {
        return $this->hasMany(PersonMovement::class);
    }
}
