<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Older accounts may predate usernames. Give them a unique public identity.
        foreach (DB::table('users')->whereNull('username')->orWhere('username', '')->orderBy('id')->get(['id']) as $user) {
            $username = 'member_'.$user->id;
            while (DB::table('users')->whereRaw('LOWER(username) = ?', [$username])->exists()) {
                $username .= '_';
            }
            DB::table('users')->where('id', $user->id)->update(['username' => $username]);
        }
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['name', 'surname']));
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('name')->default('');
            $table->string('surname')->nullable();
        });
        // Removed personal names cannot be restored; use the public username.
        DB::table('users')->update(['name' => DB::raw('username')]);
    }
};
