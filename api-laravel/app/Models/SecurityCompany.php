<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SecurityCompany extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    public function gatekeepers()
    {
        return $this->hasMany(Gatekeeper::class);
    }
}
