<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'cash_category_id', 'play_session_id', 'play_session_member_id',
        'transaction_date', 'amount', 'description',
    ];

    protected function casts(): array
    {
        return ['transaction_date' => 'date', 'amount' => 'integer'];
    }

    public function cashCategory(): BelongsTo
    {
        return $this->belongsTo(CashCategory::class);
    }

    public function playSession(): BelongsTo
    {
        return $this->belongsTo(PlaySession::class);
    }

    public function playSessionMember(): BelongsTo
    {
        return $this->belongsTo(PlaySessionMember::class);
    }
}
