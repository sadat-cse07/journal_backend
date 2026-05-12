<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('papers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->comment('Public unique identifier');
            $table->string('title', 500);
            $table->text('abstract');
            $table->json('keywords')->nullable()->comment('JSON array of keywords');
            $table->foreignId('category_id')
                  ->nullable()
                  ->constrained()
                  ->nullOnDelete();
            $table->enum('paper_type', [
                'research_article',
                'review_article',
                'case_study',
                'technical_note'
            ])->default('research_article');
            $table->enum('status', [
                'draft',
                'submitted',
                'under_review',
                'revision_required',
                'accepted',
                'rejected',
                'withdrawn'
            ])->default('draft');
            $table->foreignId('submitted_by')
                  ->constrained('users')
                  ->cascadeOnDelete()
                  ->comment('Author who submitted');
            $table->foreignId('editorial_assigned')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete()
                  ->comment('Editorial member handling this paper');
            $table->integer('current_round')
                  ->default(0)
                  ->comment('Current review round number');
            $table->timestamp('submission_date')->nullable();
            $table->timestamp('decision_date')->nullable();
            $table->timestamp('publication_date')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index('status');
            $table->index(['editorial_assigned', 'status']);
        });
        
        echo "✓ Papers table created successfully!\n";
    }

    public function down(): void
    {
        Schema::dropIfExists('papers');
    }
};