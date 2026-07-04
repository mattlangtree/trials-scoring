<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rider extends Model
{
    use HasFactory;

    public const STATUS_PLACED = 'placed';

    public const STATUS_DNF = 'dnf';

    public const STATUS_DNS = 'dns';

    public const STATUS_NC = 'nc';

    protected $guarded = [];

    public function isCompeting(): bool
    {
        return $this->status === self::STATUS_PLACED || $this->status === self::STATUS_DNF;
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function riderClass(): BelongsTo
    {
        return $this->belongsTo(RiderClass::class);
    }

    public function scores(): HasMany
    {
        return $this->hasMany(Score::class);
    }

    public function officialScores(): HasMany
    {
        return $this->scores()->where('status', Score::STATUS_OFFICIAL);
    }
}
