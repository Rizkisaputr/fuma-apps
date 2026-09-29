<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Member extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['name', 'address', 'gender', 'skill_level', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'deleted_at' => 'datetime'];
    }

    public function playSessionMembers(): HasMany
    {
        return $this->hasMany(PlaySessionMember::class);
    }

    public function playSessions(): BelongsToMany
    {
        return $this->belongsToMany(PlaySession::class, 'play_session_members')
            ->withPivot(['id', 'attended_at', 'fee_amount', 'paid_at'])
            ->withTimestamps();
    }

    public function gamePlayers(): HasMany
    {
        return $this->hasMany(GamePlayer::class);
    }

    public function games(): BelongsToMany
    {
        return $this->belongsToMany(Game::class, 'game_players')
            ->withPivot(['team', 'slot'])
            ->withTimestamps();
    }
}
