<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->text('address')->nullable()->after('name');
            $table->enum('gender', ['laki-laki', 'perempuan'])->nullable()->after('address');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE members MODIFY skill_level ENUM(\'pemula\', \'menengah\', \'pro\') NOT NULL');
        } else {
            Schema::table('members', function (Blueprint $table) {
                $table->enum('skill_level', ['pemula', 'menengah', 'pro'])->change();
            });
        }
    }

    public function down(): void
    {
        DB::table('members')->where('skill_level', 'menengah')->update(['skill_level' => 'pemula']);

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE members MODIFY skill_level ENUM(\'pro\', \'pemula\') NOT NULL');
        } else {
            Schema::table('members', function (Blueprint $table) {
                $table->enum('skill_level', ['pro', 'pemula'])->change();
            });
        }

        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn(['address', 'gender']);
        });
    }
};
