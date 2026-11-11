<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetInspection extends Model
{
    protected $fillable = [
        'asset_id',
        'stock_check_id',
        'inspector_id',
        'inspector_name',
        'condition_status',
        'location_found',
        'scan_method',
        'note',
        'inspected_at',
    ];

    protected function casts(): array
    {
        return [
            'inspected_at' => 'datetime',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function stockCheck(): BelongsTo
    {
        return $this->belongsTo(StockCheck::class);
    }

    public function inspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspector_id');
    }

    public function conditionLabel(): string
    {
        return Asset::STATUS_LABELS[$this->condition_status] ?? $this->condition_status;
    }
}
