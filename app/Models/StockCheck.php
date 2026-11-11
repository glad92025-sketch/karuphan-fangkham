<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockCheck extends Model
{
    protected $fillable = [
        'code',
        'title',
        'fiscal_year',
        'status',
        'started_at',
        'closed_at',
        'created_by',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'fiscal_year' => 'integer',
            'started_at' => 'date',
            'closed_at' => 'date',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockCheckItem::class)->latest('checked_at');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }
}
