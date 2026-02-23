<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('admin_authorizations', function (Blueprint $table) {
    $table->id();
    $table->string('code')->unique();
    $table->foreignId('super_admin_id')->constrained('users');
    $table->string('action'); // Ejemplo: 'delete_user'
    $table->timestamp('expires_at');
    $table->boolean('used')->default(false);
    $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_authorizations');
    }
};
