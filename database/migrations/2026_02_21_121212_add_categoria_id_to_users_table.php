<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {

            $table->foreignId('categoria_id')
                  ->after('password')
                  ->constrained('categorias')
                  ->cascadeOnDelete();

        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {

            $table->dropForeign(['categoria_id']);
            $table->dropColumn('categoria_id');

        });
    }
};