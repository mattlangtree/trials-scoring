<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Event extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'is_active' => 'boolean',
            'riding_ends_at' => 'datetime',
            'cards_in_at' => 'datetime',
        ];
    }

    public function riderClasses(): HasMany
    {
        return $this->hasMany(RiderClass::class);
    }

    public function sections(): HasMany
    {
        return $this->hasMany(Section::class);
    }

    public function riders(): HasMany
    {
        return $this->hasMany(Rider::class);
    }

    public function scores(): HasMany
    {
        return $this->hasMany(Score::class);
    }

    public function stagedScores(): HasMany
    {
        return $this->hasMany(StagedScore::class);
    }

    public function isRunning(): bool
    {
        return (bool) $this->riding_ends_at?->isFuture();
    }
}
