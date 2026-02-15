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

            // Required FK
            $table->foreignId('vendor_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            // Client type (default commercial)
            $table->enum('client_type', ['commercial', 'residential'])
                ->default('commercial')
                ->index();

            /*
            | Residential fields
            */
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->text('residential_address')->nullable();

            /*
            | Commercial / General fields (ALL OPTIONAL)
            */
            $table->string('business_name')->nullable();
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

            $table->string('contact_person_name')->nullable();
            $table->string('designation')->nullable();

            // Contact (explicit optional)
            $table->string('email')->nullable();
            $table->string('mobile_number')->nullable();
            $table->string('alternate_mobile_number')->nullable();

            /*
            | Address
            */
            $table->string('address_line_1')->nullable();
            $table->string('address_line_2')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('country')->nullable();
            $table->string('zip_code')->nullable();

            /*
            | Billing & Finance
            */
            $table->string('billing_name')->nullable();

            $table->enum('payment_term', [
                'net_7',
                'net_15',
                'net_30',
                'net_45',
                'net_60',
                'due_on_receipt',
            ])->default('net_30');

            $table->string('preferred_currency', 3)->default('USD');
            $table->decimal('tax_percentage', 5, 2)->nullable();

            /*
            | Extras
            */
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

            $table->enum('status', [
                'active',
                'inactive',
                'suspended',
                'archived'
            ])->default('active');

            /*
            | Audit
            */
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['vendor_id', 'status']);
            $table->index('email');
            $table->index('client_category');
        });
    }

    public function down()
    {
        Schema::dropIfExists('clients');
    }
};
