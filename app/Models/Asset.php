<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
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

    /**
     * Extract the Buddhist Era (พ.ศ.) acquisition year from date, raw text, or asset code.
     */
    public function acquiredYearBe(): ?int
    {
        if ($this->acquired_date) {
            $year = (int) $this->acquired_date->year;

            return $year >= 2400 ? $year : $year + 543;
        }

        $text = trim((string) $this->acquired_date_text);
        if ($text !== '') {
            if (preg_match('/(24\d{2}|25\d{2})/u', $text, $m)) {
                return (int) $m[1];
            }
            if (preg_match('/(?:\.|\/|-|\s)(\d{2})\s*$/u', $text, $m)) {
                $short = (int) $m[1];

                return ($short >= 80 ? 2400 : 2500) + $short;
            }
        }

        if ($this->asset_code && preg_match('/^\d+-(\d{2})-\d+/', trim($this->asset_code), $m)) {
            $short = (int) $m[1];

            return ($short >= 80 ? 2400 : 2500) + $short;
        }

        return null;
    }

    /**
     * Human-readable acquisition date string including explicit 4-digit BE year when abbreviated.
     */
    public function acquiredDisplay(): string
    {
        $text = trim((string) $this->acquired_date_text);
        $year = $this->acquiredYearBe();

        if ($text !== '') {
            if ($year && ! str_contains($text, (string) $year)) {
                return "{$text} (ปี {$year})";
            }

            return $text;
        }

        return $year ? "ปี พ.ศ. {$year}" : '-';
    }

    /**
     * Combined responsible person and position label for compact views/stickers.
     */
    public function responsibleLabel(): string
    {
        $resp = $this->responsible_person ?? '-';
        if ($this->position_title && ! str_contains((string) $this->responsible_person, $this->position_title)) {
            return "{$resp} ({$this->position_title})";
        }

        return $resp;
    }

    /**
     * Search across QR token, code, name, responsible person, location, and acquisition date/year.
     */
    public function scopeSearch(Builder $query, string $search): Builder
    {
        $term = trim($search);
        if ($term === '') {
            return $query;
        }

        $ceYear = null;
        $shortBe = null;
        if (preg_match('/^(24|25)(\d{2})$/', $term, $m)) {
            $ceYear = (int) $term - 543;
            $shortBe = $m[2];
        }

        return $query->where(function (Builder $q) use ($term, $ceYear, $shortBe) {
            $q->where('qr_token', 'like', "%{$term}%")
                ->orWhere('asset_code', 'like', "%{$term}%")
                ->orWhere('name', 'like', "%{$term}%")
                ->orWhere('specification', 'like', "%{$term}%")
                ->orWhere('responsible_person', 'like', "%{$term}%")
                ->orWhere('position_title', 'like', "%{$term}%")
                ->orWhere('location', 'like', "%{$term}%")
                ->orWhere('acquired_date_text', 'like', "%{$term}%");

            if ($ceYear !== null && $shortBe !== null) {
                $q->orWhere(function (Builder $sub) use ($ceYear) {
                    $sub->whereDate('acquired_date', '>=', "{$ceYear}-01-01")
                        ->whereDate('acquired_date', '<=', "{$ceYear}-12-31");
                })
                    ->orWhere('asset_code', 'like', "%-{$shortBe}-%")
                    ->orWhere('acquired_date_text', 'like', "%{$shortBe}");
            }
        });
    }
}
