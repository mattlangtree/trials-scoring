<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Section extends Model
{
    use HasFactory;

    /**
     * Alphabet without ambiguous characters (0/O, 1/I/L) so codes
     * survive being read off a printed sheet or a photo.
     */
    public const CODE_ALPHABET = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';

    protected $guarded = [];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function claims(): HasMany
    {
        return $this->hasMany(SectionClaim::class);
    }

    public function scores(): HasMany
    {
        return $this->hasMany(Score::class);
    }

    public static function generateClaimCode(int $eventId): string
    {
        do {
            $code = '';
            foreach (range(1, 3) as $i) {
                $code .= self::CODE_ALPHABET[random_int(0, strlen(self::CODE_ALPHABET) - 1)];
            }
        } while (static::where('event_id', $eventId)->where('claim_code', $code)->exists());

        return $code;
    }
}
