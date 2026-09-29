<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlaySession extends Model
{
    use HasFactory;

    protected $fillable = ['play_date', 'fee_amount', 'court_count', 'status'];

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
            ->withPivot(['id', 'attended_at', 'fee_amount', 'paid_at'])
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
}
