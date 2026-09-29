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

    protected $fillable = [
        'play_session_id',
        'game_number',
        'status',
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

    public function players(): BelongsToMany
    {
        return $this->belongsToMany(Member::class, 'game_players')
            ->withPivot(['team', 'slot'])
            ->withTimestamps();
    }
}
