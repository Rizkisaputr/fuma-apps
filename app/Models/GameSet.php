<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameSet extends Model
{
    use HasFactory;

    protected $fillable = [
        'game_id',
        'set_number',
        'team_a_score',
        'team_b_score',
        'winner_team',
    ];

    protected function casts(): array
    {
        return [
            'set_number' => 'integer',
            'team_a_score' => 'integer',
            'team_b_score' => 'integer',
        ];
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }
}
