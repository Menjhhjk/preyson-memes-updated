<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->boolean('comments_enabled')->default(true);
        });
        Schema::table('users', function (Blueprint $table) {
            $table->text('description')->nullable();
            $table->string('profile_visibility', 10)->default('public')->index();
            $table->boolean('email_visible')->default(false);
            $table->string('profile_background', 10)->default('default');
            $table->string('profile_color_one', 7)->default('#f8f6fa');
            $table->string('profile_color_two', 7)->default('#e9dcfa');
            $table->foreignId('pinned_post_id')->nullable()->constrained('posts')->nullOnDelete();
        });
        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->timestamp('edited_at')->nullable();
            $table->timestamps();
            $table->index(['post_id', 'created_at']);
        });
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporter_id')->nullable()->constrained('users')->nullOnDelete();
            // Keep the original identifier and snapshot after a target is removed.
            $table->string('target_type', 10);
            $table->unsignedBigInteger('target_id');
            $table->string('target_label');
            $table->text('target_snapshot')->nullable();
            $table->string('reason', 60);
            $table->string('severity', 10);
            $table->text('description')->nullable();
            $table->string('status', 15)->default('open');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('moderator_note')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->unique(['reporter_id', 'target_type', 'target_id']);
            $table->index(['target_type', 'target_id']);
            $table->index(['status', 'severity', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
        Schema::dropIfExists('comments');
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pinned_post_id');
            $table->dropColumn(['description', 'profile_visibility', 'email_visible', 'profile_background', 'profile_color_one', 'profile_color_two']);
        });
        Schema::table('posts', fn (Blueprint $table) => $table->dropColumn('comments_enabled'));
    }
};
