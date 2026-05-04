<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Persist the catalogue's per-entry sitemap metadata on screenshot_pages.
 *
 * Without these columns the SitemapWriter emits every entry with a flat
 * `type: page` and no auth flag — so `auth.login` / `auth.password-reset`
 * pages get captured through the authenticated context and the login
 * shot ends up showing the post-login dashboard instead of the form.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('screenshot_pages', function (Blueprint $table): void {
            $table->string('type', 64)->default('page')->after('label');
            $table->string('auth', 32)->nullable()->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('screenshot_pages', function (Blueprint $table): void {
            $table->dropColumn(['type', 'auth']);
        });
    }
};
