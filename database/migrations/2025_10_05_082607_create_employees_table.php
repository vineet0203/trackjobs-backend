<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->onDelete('cascade');
            $table->foreignId('vendor_id')->constrained()->onDelete('cascade');


            $table->string('employee_code');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('middle_name')->nullable();
            $table->string('preferred_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('personal_email')->unique()->nullable();
            $table->string('profile_image')->nullable();
            $table->enum('status', ['active', 'inactive', 'terminated', 'on_leave'])->default('active');

             // Audit fields
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['employee_code']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('employees');
    }
};
