<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_settings', function (Blueprint $table) {
            $table->id();
            $table->string('app_name')->default('FUMA');
            $table->string('logo_path')->nullable();
            $table->unsignedInteger('default_fee_amount')->default(15000);
            $table->unsignedTinyInteger('default_court_count')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_settings');
    }
};
