<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Asset extends Model
{
    use SoftDeletes;

    public const STATUS_LABELS = [
        'usable' => 'ใช้งานได้',
        'repair' => 'ส่งซ่อม',
        'damaged' => 'ชำรุด/รอจำหน่าย',
        'disposed' => 'จำหน่ายแล้ว',
        'lost' => 'สูญหาย',
    ];

    public const STATUS_BADGE_CLASSES = [
        'usable' => 'badge-ok',
        'repair' => 'badge-repair',
        'damaged' => 'badge-lost',
        'disposed' => 'badge-muted',
        'lost' => 'badge-lost',
    ];

    protected $fillable = [
        'qr_token',
        'excel_seq',
        'asset_code',
        'name',
        'specification',
        'category',
        'acquired_date_text',
        'acquired_date',
        'fiscal_year',
        'price',
        'responsible_person',
        'position_title',
        'department',
        'location',
        'quantity',
        'unit',
        'status',
        'review_status',
        'note',
        'photo_path',
        'last_checked_at',
        'last_checked_by',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'acquired_date' => 'date',
            'price' => 'decimal:2',
            'last_checked_at' => 'datetime',
            'fiscal_year' => 'integer',
            'quantity' => 'integer',
        ];
    }

    public function scopeApproved($query)
    {
        return $query->where('review_status', 'approved');
    }

    public function scopePendingReview($query)
    {
        return $query->where('review_status', 'pending');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(AssetMovement::class)->latest('moved_at')->latest('id');
    }

    public function maintenanceLogs(): HasMany
    {
        return $this->hasMany(MaintenanceLog::class)->latest('reported_at')->latest('id');
    }

    public function inspections(): HasMany
    {
        return $this->hasMany(AssetInspection::class)->latest('inspected_at')->latest('id');
    }

    public function stockCheckItems(): HasMany
    {
        return $this->hasMany(StockCheckItem::class);
    }

    public function lastChecker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_checked_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function badgeClass(): string
    {
        return self::STATUS_BADGE_CLASSES[$this->status] ?? 'badge-muted';
    }

    public function displayCode(): string
    {
        return $this->asset_code ?: $this->qr_token;
    }

    public function scanUrl(): string
    {
        return route('scan.show', ['token' => $this->qr_token]);
    }

    public static function generateNextToken(): string
    {
        $maxId = (int) (self::withTrashed()->max('id') ?? 0);
        $next = $maxId + 1;

        do {
            $candidate = sprintf('FKSP-%04d', $next);
            $next++;
        } while (self::withTrashed()->where('qr_token', $candidate)->exists());

        return $candidate;
    }

    /**
     * Distinct existing position titles merged with common Thai SAO positions.
     */
    public static function positionSuggestions(): \Illuminate\Support\Collection
    {
        $defaults = collect([
            'ปลัด อบต.',
            'รองปลัด อบต.',
            'หัวหน้าสำนักปลัด',
            'นักทรัพยากรบุคคล',
            'นักวิเคราะห์นโยบายและแผน',
            'นิติกร',
            'นักวิชาการคอมพิวเตอร์',
            'เจ้าพนักงานธุรการ',
            'เจ้าพนักงานป้องกันและบรรเทาสาธารณภัย',
            'ผู้ช่วยเจ้าพนักงานธุรการ',
            'ผู้ช่วยเจ้าพนักงานป้องกันฯ',
            'พนักงานขับรถยนต์',
            'นักการภารโรง',
            'ประธานสภา อบต.',
            'สมาชิกสภา อบต.',
        ]);

        $fromDb = self::whereNotNull('position_title')
            ->where('position_title', '!=', '')
            ->distinct()
            ->pluck('position_title');

        return $fromDb->merge($defaults)->unique()->values();
    }
}
