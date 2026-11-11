<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceLog extends Model
{
    public const STATUS_LABELS = [
        'pending' => 'รอซ่อม',
        'in_progress' => 'กำลังดำเนินการซ่อม',
        'completed' => 'ซ่อมเสร็จแล้ว',
        'unrepairable' => 'ซ่อมไม่ได้ (รอจำหน่าย)',
    ];

    protected $fillable = [
        'asset_id',
        'reported_by',
        'reporter_name',
        'issue_description',
        'maintenance_status',
        'vendor_name',
        'repair_cost',
        'resolution_note',
        'reported_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'repair_cost' => 'decimal:2',
            'reported_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->maintenance_status] ?? $this->maintenance_status;
    }
}
