<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['body', 'created_by'])]
class DealNote extends Model
{
    const UPDATED_AT = null;

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
