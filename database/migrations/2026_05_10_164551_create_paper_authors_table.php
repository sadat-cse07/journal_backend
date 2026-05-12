<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paper_authors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paper_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->integer('author_order')->comment('Order of authors (1,2,3...)');
            $table->boolean('is_corresponding')->default(false);
            $table->text('contribution')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            // Each user can only be author once per paper
            $table->unique(['paper_id', 'user_id'], 'unique_paper_author');
            $table->index('user_id');
        });
        
        echo "✓ Paper authors table created successfully!\n";
    }

    public function down(): void
    {
        Schema::dropIfExists('paper_authors');
    }
};