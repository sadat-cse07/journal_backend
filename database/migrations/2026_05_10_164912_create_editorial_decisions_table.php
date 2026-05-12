<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('editorial_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_round_id')->constrained()->cascadeOnDelete();
            $table->foreignId('decision_by')->constrained('users')->cascadeOnDelete();
            $table->enum('decision', [
                'accept',
                'minor_revision',
                'major_revision',
                'reject'
            ]);
            $table->text('comments')->nullable();
            $table->timestamp('made_at')->useCurrent();
            $table->timestamps();
            $table->softDeletes();
        });
        
        echo "✓ Editorial decisions table created successfully!\n";
    }

    public function down(): void
    {
        Schema::dropIfExists('editorial_decisions');
    }
};