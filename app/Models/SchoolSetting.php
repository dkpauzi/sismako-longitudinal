<?php
// app/Models/SchoolSetting.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchoolSetting extends Model
{
    protected $guarded = [];

    protected $casts = [
        'default_kkm' => 'integer',
        'show_score_sd' => 'boolean',
    ];
}