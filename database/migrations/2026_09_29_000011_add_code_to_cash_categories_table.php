<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_categories', function (Blueprint $table) {
            $table->string('code')->nullable()->unique()->after('id');
        });

        foreach ([
            'Iuran' => 'dues',
            'Sponsorship' => 'sponsorship',
            'Booking Court' => 'court_booking',
            'Shuttlecock' => 'shuttlecock',
            'Refreshment' => 'refreshment',
        ] as $name => $code) {
            DB::table('cash_categories')->where('name', $name)->update(['code' => $code]);
        }
    }

    public function down(): void
    {
        Schema::table('cash_categories', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn('code');
        });
    }
};
