<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PlaySessionMember extends Model
{
    use HasFactory;

    protected $fillable = [
        'play_session_id', 'member_id', 'attended_at', 'fee_amount', 'paid_at',
    ];

    protected function casts(): array
    {
        return ['attended_at' => 'datetime', 'fee_amount' => 'integer', 'paid_at' => 'datetime'];
    }

    public function playSession(): BelongsTo
    {
        return $this->belongsTo(PlaySession::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class)->withTrashed();
    }

    public function cashTransaction(): HasOne
    {
        return $this->hasOne(CashTransaction::class);
    }

    public function isPaid(): bool
    {
        return $this->paid_at !== null;
    }
}
