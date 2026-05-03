<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('screenshot_pages', function (Blueprint $table): void {
            $table->id();
            $table->string('panel', 64)->index();
            $table->string('slug', 255);
            $table->enum('viewport', ['desktop', 'tablet', 'mobile']);
            $table->enum('mode', ['light', 'dark']);
            $table->string('url', 512);
            $table->string('label', 255)->nullable();
            $table->integer('sort')->default(1000);
            $table->boolean('included')->default(true)->index();
            $table->timestamp('removed_at')->nullable();
            $table->timestamps();

            $table->unique(['panel', 'slug', 'viewport', 'mode'], 'screenshot_pages_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('screenshot_pages');
    }
};
