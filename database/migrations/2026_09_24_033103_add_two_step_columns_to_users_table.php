<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('two_step_code_hash', 64)->nullable()->after('last_login_at');
            $table->timestamp('two_step_code_expires_at')->nullable()->after('two_step_code_hash');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['two_step_code_hash', 'two_step_code_expires_at']);
        });
    }
};
