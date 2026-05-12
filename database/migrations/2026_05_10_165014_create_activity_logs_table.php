<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('paper_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 100);
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->softDeletes();
            
            $table->index(['user_id', 'created_at']);
            $table->index('paper_id');
        });
        
        echo "✓ Activity logs table created successfully!\n";
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};