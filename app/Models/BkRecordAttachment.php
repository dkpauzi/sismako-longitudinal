<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BkRecordAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'record_id',
        'file_path',
        'file_type',
    ];

    public function record(): BelongsTo
    {
        return $this->belongsTo(BkCounselingRecord::class, 'record_id');
    }
}
