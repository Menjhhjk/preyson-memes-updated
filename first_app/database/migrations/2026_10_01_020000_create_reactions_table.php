<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('emoji', 32);
            $table->timestamps();
            $table->unique(['post_id', 'user_id']);
        });

        foreach (DB::table('posts')->where('media_type', 'image')->cursor() as $post) {
            if (Storage::disk('public')->exists($post->media_path)
                && Storage::disk('public')->mimeType($post->media_path) === 'image/gif') {
                DB::table('posts')->where('id', $post->id)->update(['media_type' => 'gif']);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('reactions');
        DB::table('posts')->where('media_type', 'gif')->update(['media_type' => 'image']);
    }
};
