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
        Schema::connection('mysql')->create('site_content_documents', function (Blueprint $table) {
            $table->id();
            $table->string('document_key', 100)->unique();
            $table->json('live_payload')->nullable();
            $table->json('draft_payload')->nullable();
            $table->string('base_hash', 64)->nullable();
            $table->unsignedInteger('version')->default(0);
            $table->string('updated_by', 150)->nullable();
            $table->timestamps();
        });
        Schema::connection('mysql')->create('site_content_revisions', function (Blueprint $table) {
            $table->id();
            $table->string('document_key', 100)->index();
            $table->json('payload');
            $table->string('action', 30);
            $table->string('created_by', 150);
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('mysql')->dropIfExists('site_content_revisions');
        Schema::connection('mysql')->dropIfExists('site_content_documents');
    }
};
