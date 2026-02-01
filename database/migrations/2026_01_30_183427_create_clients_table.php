<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();

            // Vendor relationship
            $table->foreignId('vendor_id')
                ->constrained()
                ->cascadeOnDelete();

            // User relationship
            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();


            // Basic Business Information
            $table->string('business_name');
            $table->enum('business_type', [
                'individual',
                'sole_proprietorship',
                'partnership',
                'llc',
                'corporation',
                'non_profit',
                'government',
                'other'
            ])->nullable();
            $table->string('industry')->nullable();
            $table->string('business_registration_number')->nullable();

            // Primary Contact Information
            $table->string('contact_person_name')->nullable();
            $table->string('designation')->nullable();
            $table->string('email')->nullable();
            $table->string('mobile_number')->nullable();
            $table->string('alternate_mobile_number')->nullable();

            // Business Address (Primary Address)
            $table->string('address_line_1')->nullable();
            $table->string('address_line_2')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('country')->nullable();
            $table->string('zip_code')->nullable();

            // Billing & Financial Details
            $table->string('billing_name')->nullable();
            $table->boolean('same_as_business_address')->default(true);

            // Billing Address (if different from business address)
            $table->string('billing_address_line_1')->nullable();
            $table->string('billing_address_line_2')->nullable();
            $table->string('billing_city')->nullable();
            $table->string('billing_state')->nullable();
            $table->string('billing_country')->nullable();
            $table->string('billing_zip_code')->nullable();

            $table->enum('payment_term', [
                'net_7',
                'net_15',
                'net_30',
                'net_45',
                'net_60',
                'due_on_receipt',
                'custom'
            ])->default('net_30');
            $table->string('custom_payment_term')->nullable();

            $table->string('preferred_currency', 3)->default('USD');
            $table->decimal('tax_percentage', 5, 2)->nullable();
            $table->string('tax_id')->nullable();

            // Additional Business Details
            $table->string('website_url')->nullable();
            $table->string('logo_path')->nullable();

            $table->enum('client_category', [
                'premium',
                'regular',
                'vip',
                'strategic',
                'new',
                'at_risk'
            ])->default('regular');

            $table->text('notes')->nullable();

            // Status & Actions
            $table->enum('status', [
                'active',
                'inactive',
                'suspended',
                'archived'
            ])->default('active');

            $table->boolean('is_verified')->default(false);
            $table->timestamp('verified_at')->nullable();

            // Audit fields
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['vendor_id', 'status']);
            $table->index('business_name');
            $table->index('email');
            $table->index('client_category');
        });
    }

    public function down()
    {
        Schema::dropIfExists('clients');
    }
};
