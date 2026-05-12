<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_round_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reviewer_id')->constrained('users')->cascadeOnDelete();
            $table->enum('status', [
                'pending',       // Invitation sent, no response yet
                'accepted',      // Accepted but not started
                'declined',      // Declined the invitation
                'in_progress',   // Currently reviewing
                'completed'      // Review submitted
            ])->default('pending');
            $table->enum('decision', [
                'accept',
                'minor_revision',
                'major_revision',
                'reject'
            ])->nullable();
            $table->text('confidential_comments')->nullable()->comment('Comments only for editor');
            $table->text('comments_for_author')->nullable();
            $table->text('comments_for_editor')->nullable();
            $table->tinyInteger('rating_originality')->default(0);
            $table->tinyInteger('rating_methodology')->default(0);
            $table->tinyInteger('rating_clarity')->default(0);
            $table->tinyInteger('rating_significance')->default(0);
            $table->tinyInteger('overall_recommendation')->default(0);
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('due_date')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            // Each reviewer can review only once per round
            $table->unique(['review_round_id', 'reviewer_id'], 'unique_reviewer_per_round');
            $table->index(['reviewer_id', 'status']);
            $table->index('due_date');
        });
        
        echo "✓ Reviews table created successfully!\n";
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};