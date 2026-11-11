<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 30)->default('staff')->after('password');
            $table->string('position', 150)->nullable()->after('role');
            $table->string('department', 150)->default('สำนักปลัด')->after('position');
            $table->string('phone', 50)->nullable()->after('department');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'position', 'department', 'phone']);
        });
    }
};
