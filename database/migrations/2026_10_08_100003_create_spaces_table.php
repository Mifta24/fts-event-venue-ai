<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A bookable hall, garden or room of a venue. `layouts` maps each setup
     * style (banquet, theatre, cocktail ...) to the guests it holds.
     */
    public function up(): void
    {
        Schema::create('spaces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venue_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->json('translations')->nullable();
            $table->string('space_type')->default('indoor');
            $table->unsignedSmallInteger('size_sqm')->nullable();
            $table->decimal('ceiling_height_m', 4, 1)->nullable();
            $table->string('level_label', 30)->nullable();
            $table->json('layouts')->nullable();
            $table->boolean('av_included')->default(false);
            $table->boolean('catering_available')->default(false);
            $table->decimal('catering_price', 12, 2)->nullable();
            $table->decimal('base_price', 12, 2);
            $table->json('amenities')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->softDeletes();
            $table->timestamps();
            $table->unique(['venue_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spaces');
    }
};
