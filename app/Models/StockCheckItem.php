<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockCheckItem extends Model
{
    public const STATUS_LABELS = [
        'found_usable' => 'พบ - ใช้งานได้',
        'found_repair' => 'พบ - ควรส่งซ่อม',
        'found_damaged' => 'พบ - ชำรุด',
        'not_found' => 'ไม่พบครุภัณฑ์',
    ];

    protected $fillable = [
        'stock_check_id',
        'asset_id',
        'checked_by',
        'checker_name',
        'check_status',
        'actual_responsible',
        'actual_position',
        'actual_location',
        'scan_method',
        'remark',
        'checked_at',
    ];

    protected function casts(): array
    {
        return [
            'checked_at' => 'datetime',
        ];
    }

    public function stockCheck(): BelongsTo
    {
        return $this->belongsTo(StockCheck::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function checker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by');
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->check_status] ?? $this->check_status;
    }

    public static function mapCheckStatusToAssetStatus(string $checkStatus): string
    {
        return match ($checkStatus) {
            'found_usable' => 'usable',
            'found_repair' => 'repair',
            'found_damaged' => 'damaged',
            'not_found' => 'lost',
            default => 'usable',
        };
    }
}
