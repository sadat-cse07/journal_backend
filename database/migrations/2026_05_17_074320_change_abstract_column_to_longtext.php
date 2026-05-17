<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('papers', function (Blueprint $table) {
            $table->longText('abstract')->change();
        });
    }

    public function down(): void
    {
        Schema::table('papers', function (Blueprint $table) {
            $table->text('abstract')->change();
        });
    }
};
