<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class ApplicationSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'app_name',
        'logo_path',
        'default_fee_amount',
        'default_court_count',
    ];

    protected function casts(): array
    {
        return [
            'default_fee_amount' => 'integer',
            'default_court_count' => 'integer',
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrNew([], [
            'app_name' => 'FUMA',
            'default_fee_amount' => 15000,
            'default_court_count' => 1,
        ]);
    }

    public function logoUrl(): string
    {
        return $this->logo_path
            ? Storage::disk('public')->url($this->logo_path)
            : asset('images/fuma-logo.svg');
    }

    public function logoAbsolutePath(): string
    {
        return $this->logo_path
            ? Storage::disk('public')->path($this->logo_path)
            : public_path('images/fuma-logo.svg');
    }
}
