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
        Schema::create('classified_posts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('position_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('side_hustle_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('author_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('title');
            $table->text('content');
            $table->enum('status', ['OPEN', 'FILLED', 'CLOSED'])->default('OPEN');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('classified_posts');
    }
};
