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
            $table->unsignedInteger('bonus_super_reactions')->default(0);
            $table->unsignedInteger('bonus_boosts')->default(0);
            $table->timestamp('corner_unlocked_at')->nullable();
        });
        Schema::table('comments', function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()->constrained('comments')->cascadeOnDelete();
            $table->foreignId('reply_to_id')->nullable()->constrained('comments')->nullOnDelete();
            $table->boolean('hearted_by_owner')->default(false);
        });
        Schema::table('posts', function (Blueprint $table) {
            $table->foreignId('pinned_comment_id')->nullable()->constrained('comments')->nullOnDelete();
            $table->timestamp('ten_reactions_notified_at')->nullable();
        });
        Schema::create('member_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 40);
            $table->string('title');
            $table->text('message');
            $table->string('target_type', 30)->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->string('dedupe_key', 120)->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'dedupe_key']);
            $table->index(['user_id', 'read_at', 'id']);
        });
        Schema::create('posting_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('post_id')->nullable()->constrained()->nullOnDelete();
            $table->date('posted_on');
            $table->unsignedTinyInteger('day_number');
            $table->json('reward');
            $table->timestamps();
            $table->unique(['user_id', 'posted_on']);
            $table->unique(['user_id', 'day_number']);
        });
        Schema::create('super_reaction_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60);
            $table->string('slug', 80)->unique();
            $table->string('gif_path');
            $table->string('sound_path');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('post_power_uses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Deleting content must not refund spent charges.
            $table->foreignId('post_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('super_reaction_type_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 10);
            $table->string('source', 12);
            $table->uuid('request_key');
            $table->json('effect');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'request_key']);
            $table->index(['user_id', 'kind', 'source', 'created_at']);
            $table->index(['kind', 'expires_at', 'post_id']);
        });
        foreach (['stellar' => 'Absolutely Stellar', 'hype' => 'Maximum Hype', 'love' => 'Big Love'] as $slug => $name) {
            DB::table('super_reaction_types')->insert(['name' => $name, 'slug' => $slug, 'gif_path' => '@demo/'.$slug.'.gif',
                'sound_path' => '@demo/'.$slug.'.wav', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        }
        Schema::create('member_warnings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('message');
            $table->timestamps();
        });
        Schema::create('corner_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->text('description');
            $table->string('status', 12)->default('pending')->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_note')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('corner_requests');
        Schema::dropIfExists('member_warnings');
        Schema::dropIfExists('post_power_uses');
        Schema::dropIfExists('super_reaction_types');
        Schema::dropIfExists('posting_days');
        Schema::dropIfExists('member_notifications');
        Schema::table('posts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pinned_comment_id');
            $table->dropColumn('ten_reactions_notified_at');
        });
        Schema::table('comments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reply_to_id');
            $table->dropConstrainedForeignId('parent_id');
            $table->dropColumn('hearted_by_owner');
        });
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['bonus_super_reactions', 'bonus_boosts', 'corner_unlocked_at']));
    }
};
