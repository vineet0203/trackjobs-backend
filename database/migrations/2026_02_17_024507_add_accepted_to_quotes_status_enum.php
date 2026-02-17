<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        DB::statement("
        ALTER TABLE quotes 
        MODIFY status ENUM(
            'draft','sent','viewed','expired',
            'pending','approved','rejected','accepted'
        ) DEFAULT 'draft'
    ");
    }

    public function down()
    {
        DB::statement("
        ALTER TABLE quotes 
        MODIFY status ENUM(
            'draft','sent','viewed','expired',
            'pending','approved','rejected'
        ) DEFAULT 'draft'
    ");
    }
};
