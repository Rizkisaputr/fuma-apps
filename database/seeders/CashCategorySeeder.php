<?php

namespace Database\Seeders;

use App\Models\CashCategory;
use Illuminate\Database\Seeder;

class CashCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['code' => 'dues', 'name' => 'Iuran', 'type' => 'income'],
            ['code' => 'sponsorship', 'name' => 'Sponsorship', 'type' => 'income'],
            ['code' => 'other_income', 'name' => 'Lain-lain', 'type' => 'income'],
            ['code' => 'court_booking', 'name' => 'Booking Court', 'type' => 'expense'],
            ['code' => 'shuttlecock', 'name' => 'Shuttlecock', 'type' => 'expense'],
            ['code' => 'refreshment', 'name' => 'Refreshment', 'type' => 'expense'],
            ['code' => 'other_expense', 'name' => 'Lain-lain', 'type' => 'expense'],
        ];

        foreach ($categories as $category) {
            CashCategory::query()->firstOrCreate(
                ['code' => $category['code']],
                ['name' => $category['name'], 'type' => $category['type'], 'is_active' => true],
            );
        }
    }
}
