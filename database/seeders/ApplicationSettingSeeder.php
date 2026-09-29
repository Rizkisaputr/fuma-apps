<?php

namespace Database\Seeders;

use App\Models\ApplicationSetting;
use Illuminate\Database\Seeder;

class ApplicationSettingSeeder extends Seeder
{
    public function run(): void
    {
        ApplicationSetting::query()->firstOrCreate([], [
            'app_name' => 'FUMA',
            'default_fee_amount' => 15000,
            'default_court_count' => 1,
        ]);
    }
}
