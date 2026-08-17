<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Gatekeeper extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $hidden = ['cpf'];

    public function company()
    {
        return $this->belongsTo(SecurityCompany::class, 'security_company_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function shifts()
    {
        return $this->hasMany(GatekeeperShift::class);
    }
}
