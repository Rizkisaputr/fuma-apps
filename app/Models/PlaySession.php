<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlaySession extends Model
{
    use HasFactory;

    public const TYPE_FUN_MATCH = 'fun_match';

    public const TYPE_INTERNAL_TOURNAMENT = 'internal_tournament';

    protected $fillable = [
        'play_date', 'session_type', 'fee_amount', 'court_count', 'status',
    ];

    protected function casts(): array
    {
        return ['play_date' => 'date', 'fee_amount' => 'integer', 'court_count' => 'integer'];
    }

    public function sessionMembers(): HasMany
    {
        return $this->hasMany(PlaySessionMember::class);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(Member::class, 'play_session_members')
            ->withPivot(['id', 'attended_at', 'fee_amount', 'paid_at', 'is_duty_admin'])
            ->withTimestamps();
    }

    public function games(): HasMany
    {
        return $this->hasMany(Game::class);
    }

    public function cashTransactions(): HasMany
    {
        return $this->hasMany(CashTransaction::class);
    }

    /** @return array<string, string> */
    public static function typeOptions(): array
    {
        return [
            self::TYPE_FUN_MATCH => 'Fun Match',
            self::TYPE_INTERNAL_TOURNAMENT => 'Turnamen internal FUMA',
        ];
    }

    public function typeLabel(): string
    {
        if ($this->session_type === 'regular') {
            return self::typeOptions()[self::TYPE_FUN_MATCH];
        }

        return self::typeOptions()[$this->session_type] ?? 'Sesi lainnya';
    }

    public function typeSubtitle(): ?string
    {
        return match ($this->session_type) {
            self::TYPE_FUN_MATCH, 'regular' => 'Main Sesama Member FUMA',
            self::TYPE_INTERNAL_TOURNAMENT => 'Turnamen Antar Member FUMA',
            default => null,
        };
    }
}
