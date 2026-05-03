<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('screenshot_captures', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('screenshot_page_id')
                ->constrained('screenshot_pages')
                ->cascadeOnDelete();
            $table->string('tag', 64)->index();
            $table->string('s3_disk', 64);
            $table->string('s3_key', 1024);
            $table->string('etag', 64);
            $table->unsignedBigInteger('size');
            $table->timestamp('captured_at')->index();
            $table->enum('status', ['pending', 'approved', 'changes_requested'])
                ->default('pending')
                ->index();
            $table->text('comment')->nullable();
            $table->string('reviewed_by_type')->nullable();
            $table->unsignedBigInteger('reviewed_by_id')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('ticket_url', 512)->nullable();
            $table->timestamps();

            $table->unique(
                ['screenshot_page_id', 'tag', 'etag'],
                'screenshot_captures_unique',
            );
            $table->index(['reviewed_by_type', 'reviewed_by_id'], 'screenshot_captures_reviewer');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('screenshot_captures');
    }
};
