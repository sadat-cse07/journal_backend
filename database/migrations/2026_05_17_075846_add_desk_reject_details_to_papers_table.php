<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('papers', function (Blueprint $table) {
            $table->string('desk_reject_reason')->nullable()->after('status');
            $table->text('desk_reject_comments')->nullable()->after('desk_reject_reason');
        });
    }

    public function down(): void
    {
        Schema::table('papers', function (Blueprint $table) {
            $table->dropColumn(['desk_reject_reason', 'desk_reject_comments']);
        });
    }
};
