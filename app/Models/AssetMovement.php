<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetMovement extends Model
{
    public const TYPE_LABELS = [
        'register' => 'ลงทะเบียนเข้า',
        'assign' => 'จัดสรรเข้าสำนักปลัด',
        'transfer' => 'โอนย้าย/เปลี่ยนผู้รับผิดชอบ',
        'status_change' => 'เปลี่ยนสภาพครุภัณฑ์',
        'dispose' => 'จำหน่ายออก',
    ];

    protected $fillable = [
        'asset_id',
        'user_id',
        'movement_type',
        'from_responsible',
        'to_responsible',
        'from_location',
        'to_location',
        'from_status',
        'to_status',
        'reference_doc',
        'remark',
        'moved_at',
    ];

    protected function casts(): array
    {
        return [
            'moved_at' => 'datetime',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function typeLabel(): string
    {
        return self::TYPE_LABELS[$this->movement_type] ?? $this->movement_type;
    }
}
