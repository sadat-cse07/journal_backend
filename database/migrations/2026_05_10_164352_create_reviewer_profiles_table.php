<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviewer_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                  ->unique()
                  ->constrained()
                  ->cascadeOnDelete()
                  ->comment('Reference to users table');
            $table->json('expertise_keywords')
                  ->nullable()
                  ->comment('JSON array of expertise areas');
            $table->integer('max_reviews')
                  ->default(5)
                  ->comment('Maximum concurrent reviews allowed');
            $table->integer('current_reviews')
                  ->default(0)
                  ->comment('Current active reviews count');
            $table->enum('availability_status', ['available', 'busy', 'unavailable'])
                  ->default('available')
                  ->comment('Current availability');
            $table->decimal('average_rating', 3, 2)
                  ->default(0.00)
                  ->comment('Average review quality rating');
            $table->integer('total_reviews_completed')
                  ->default(0)
                  ->comment('Lifetime reviews completed');
            $table->timestamps();
            $table->softDeletes();
        });
        
        echo "✓ Reviewer profiles table created successfully!\n";
    }

    public function down(): void
    {
        Schema::dropIfExists('reviewer_profiles');
    }
};