<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchoolActivity extends Model
{
    protected $guarded = [];

    public function school_profile(): BelongsTo
    {
        return $this->belongsTo(SchoolProfile::class);
    }
}
