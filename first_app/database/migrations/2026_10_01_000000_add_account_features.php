<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('member');
            $table->string('avatar_path')->nullable();
            $table->timestamp('premium_expires_at')->nullable();
            $table->timestamp('terms_accepted_at')->nullable();
            $table->string('terms_version', 30)->nullable();
        });
        DB::table('users')->where('is_admin', true)->update(['role' => 'admin']);
        Schema::table('posts', function (Blueprint $table) {
            $table->index(['media_type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('posts', fn (Blueprint $table) => $table->dropIndex(['media_type', 'created_at']));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn([
            'role', 'avatar_path', 'premium_expires_at', 'terms_accepted_at', 'terms_version',
        ]));
    }
};
