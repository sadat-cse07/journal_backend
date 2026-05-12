<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->comment('Public unique identifier');
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('email', 255)->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('affiliation', 255)->nullable()->comment('University or organization');
            $table->string('department', 255)->nullable();
            $table->string('orcid_id', 20)->unique()->nullable()->comment('ORCID identifier');
            $table->string('phone', 20)->nullable();
            $table->text('bio')->nullable();
            $table->string('avatar_path', 255)->nullable();
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete()->comment('Who created this user');
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes()->comment('For soft delete functionality');
            
            // Indexes for better performance
            $table->index(['status', 'email']);
        });
        
        // Output message
        echo "✓ Users table created successfully!\n";
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};