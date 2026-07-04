<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Score extends Model
{
    use HasFactory;

    public const STATUS_OFFICIAL = 'official';

    public const STATUS_SELF = 'self';

    /**
     * Observed trials marks: clean, 1 dab, 2 dabs, 3+ dabs, failure.
     */
    public const VALID_POINTS = [0, 1, 2, 3, 5];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'recorded_at' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function rider(): BelongsTo
    {
        return $this->belongsTo(Rider::class);
    }

    public function sectionClaim(): BelongsTo
    {
        return $this->belongsTo(SectionClaim::class);
    }

    public function isOfficial(): bool
    {
        return $this->status === self::STATUS_OFFICIAL;
    }
}
