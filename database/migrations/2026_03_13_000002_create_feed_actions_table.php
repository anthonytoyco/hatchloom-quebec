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
        Schema::create('feed_actions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('feed_item_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->enum('action_type', ['like', 'comment']);
            $table->text('content')->nullable();

            $table->timestamps();

            // Prevents duplicate likes from the same user on the same post
            $table->unique(['feed_item_id', 'user_id', 'action_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('feed_actions');
    }
};
