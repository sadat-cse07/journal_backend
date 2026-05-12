<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Check if permissions table exists and group column doesn't exist
        if (Schema::hasTable('permissions') && !Schema::hasColumn('permissions', 'group')) {
            Schema::table('permissions', function (Blueprint $table) {
                $table->string('group')->nullable()->after('guard_name');
            });
            
            echo "✓ Added 'group' column to permissions table\n";
        } else {
            echo "→ 'group' column already exists or permissions table not found\n";
        }
        
        // Also add description to roles if not exists
        if (Schema::hasTable('roles') && !Schema::hasColumn('roles', 'description')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->string('description')->nullable()->after('guard_name');
            });
            
            echo "✓ Added 'description' column to roles table\n";
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('permissions') && Schema::hasColumn('permissions', 'group')) {
            Schema::table('permissions', function (Blueprint $table) {
                $table->dropColumn('group');
            });
        }
        
        if (Schema::hasTable('roles') && Schema::hasColumn('roles', 'description')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->dropColumn('description');
            });
        }
    }
};