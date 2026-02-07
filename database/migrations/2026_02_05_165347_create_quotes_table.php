<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotes', function (Blueprint $table) {
            $table->id();
            
            // Quote Details (Section 1)
            $table->string('quote_number')->unique();
            $table->string('title');
            $table->string('client_name');
            $table->string('client_email');
            
            // Pricing Summary (Section 3)
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->enum('deposit_type', ['none', 'percentage', 'fixed', 'default'])->default('none');
            $table->decimal('deposit_amount', 12, 2)->nullable();
            
            // Client Approval (Section 4)
            $table->enum('status', ['draft', 'sent', 'pending', 'approved', 'rejected', 'expired'])->default('draft');
            $table->text('client_signature')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            
            // Follow-ups (Section 5)
            $table->timestamp('follow_up_at')->nullable();
            $table->enum('reminder_type', ['none', 'email', 'sms'])->default('none');
            $table->enum('follow_up_status', ['scheduled', 'completed', 'cancelled'])->default('scheduled');
            
            // Meta
            $table->timestamp('expires_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index('quote_number');
            $table->index('client_email');
            $table->index('status');
            $table->index('created_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotes');
    }
};