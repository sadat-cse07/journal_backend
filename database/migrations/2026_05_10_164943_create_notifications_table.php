<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 100)->comment('notification, reminder, alert, etc.');
            $table->string('title', 255);
            $table->text('message');
            $table->json('data')->nullable()->comment('Additional data like paper_id, etc.');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['user_id', 'read_at']);
        });
        
        echo "✓ Notifications table created successfully!\n";
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};