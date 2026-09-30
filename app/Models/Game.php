<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Game extends Model
{
    use HasFactory;

    public const FORMAT_SINGLE_SET = 'single_set';

    public const FORMAT_ROTATION = 'rotation_2_sets';

    public const FORMAT_BEST_OF_THREE = 'best_of_three';

    protected $fillable = [
        'play_session_id',
        'game_number',
        'status',
        'game_format',
        'point_target',
        'winner_team',
        'team_a_score',
        'team_b_score',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'game_number' => 'integer',
            'point_target' => 'integer',
            'team_a_score' => 'integer',
            'team_b_score' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function playSession(): BelongsTo
    {
        return $this->belongsTo(PlaySession::class);
    }

    public function gamePlayers(): HasMany
    {
        return $this->hasMany(GamePlayer::class);
    }

    public function gameSets(): HasMany
    {
        return $this->hasMany(GameSet::class)->orderBy('set_number');
    }

    public function players(): BelongsToMany
    {
        return $this->belongsToMany(Member::class, 'game_players')
            ->withPivot(['team', 'slot'])
            ->withTimestamps();
    }

    public function formatLabel(): string
    {
        return match ($this->game_format) {
            self::FORMAT_ROTATION => '2 set × 11 (tanpa rubber)',
            self::FORMAT_BEST_OF_THREE => 'Best of 3 × 21',
            default => '1 set (data lama)',
        };
    }

    public function resultLabel(): string
    {
        if ($this->status !== 'completed') {
            return match ($this->status) {
                'playing' => 'Berlangsung',
                default => 'Menunggu',
            };
        }

        if ($this->winner_team) {
            return 'Tim '.$this->winner_team.' menang';
        }

        return $this->game_format === self::FORMAT_ROTATION ? 'Seri' : 'Selesai';
    }

    public function scoreSummary(): string
    {
        $sets = $this->relationLoaded('gameSets') ? $this->gameSets : $this->gameSets()->get();

        if ($sets->isNotEmpty()) {
            return $sets
                ->map(fn (GameSet $set): string => 'S'.$set->set_number.' '.$set->team_a_score.'–'.$set->team_b_score)
                ->join(' · ');
        }

        return $this->team_a_score !== null && $this->team_b_score !== null
            ? $this->team_a_score.'–'.$this->team_b_score
            : '-';
    }
}
