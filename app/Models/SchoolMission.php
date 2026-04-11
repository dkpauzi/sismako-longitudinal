<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchoolMission extends Model
{
    protected $guarded = [];

    public function school_profile()
    {
        return $this->belongsTo(SchoolProfile::class);
    }
}
