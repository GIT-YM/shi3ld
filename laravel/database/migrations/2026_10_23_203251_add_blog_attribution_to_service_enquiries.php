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
        Schema::connection('mysql')->table('service_enquiries', function (Blueprint $table): void {
            $table->unsignedInteger('blog_post_id')->nullable()->index();
            $table->text('blog_attribution_token')->nullable();
            $table->timestamp('blog_attributed_at')->nullable();
            $table->timestamp('blog_conversion_recorded_at')->nullable();
            $table->string('blog_source_title')->nullable();
            $table->string('blog_source_url', 1000)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('mysql')->table('service_enquiries', function (Blueprint $table): void {
            $table->dropIndex(['blog_post_id']);
            $table->dropColumn(['blog_post_id', 'blog_attribution_token', 'blog_attributed_at', 'blog_conversion_recorded_at', 'blog_source_title', 'blog_source_url']);
        });
    }
};
