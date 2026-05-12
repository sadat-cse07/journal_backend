<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paper_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paper_id')->constrained()->cascadeOnDelete();
            $table->string('file_name', 255);
            $table->string('file_path', 500);
            $table->enum('file_type', [
                'manuscript',
                'cover_letter',
                'supplementary',
                'revision',
                'figure',
                'table'
            ]);
            $table->bigInteger('file_size')->nullable()->comment('File size in bytes');
            $table->string('mime_type', 100)->nullable();
            $table->integer('version')->default(1);
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('uploaded_at')->useCurrent();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['paper_id', 'version']);
        });
        
        echo "✓ Paper files table created successfully!\n";
    }

    public function down(): void
    {
        Schema::dropIfExists('paper_files');
    }
};