<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        foreach ([
            ['code' => 'dues', 'name' => 'Iuran', 'type' => 'income'],
            ['code' => 'sponsorship', 'name' => 'Sponsorship', 'type' => 'income'],
            ['code' => 'other_income', 'name' => 'Lain-lain', 'type' => 'income'],
            ['code' => 'court_booking', 'name' => 'Booking Court', 'type' => 'expense'],
            ['code' => 'shuttlecock', 'name' => 'Shuttlecock', 'type' => 'expense'],
            ['code' => 'refreshment', 'name' => 'Refreshment', 'type' => 'expense'],
            ['code' => 'other_expense', 'name' => 'Lain-lain', 'type' => 'expense'],
        ] as $category) {
            DB::table('cash_categories')->insertOrIgnore([
                ...$category,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        DB::table('cash_categories')
            ->where('code', 'dues')
            ->update(['is_active' => true, 'updated_at' => $now]);
    }

    public function down(): void
    {
        DB::table('cash_categories')
            ->whereIn('code', ['other_income', 'other_expense'])
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')
                    ->from('cash_transactions')
                    ->whereColumn('cash_transactions.cash_category_id', 'cash_categories.id');
            })
            ->delete();
    }
};
