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
        Schema::connection('mysql')->create('site_builder_assets', function (Blueprint $table): void {
            $table->id();
            $table->string('asset_key', 32)->unique();
            $table->string('kind', 20);
            $table->string('title');
            $table->mediumText('initial_payload');
            $table->mediumText('live_payload')->nullable();
            $table->timestamps();
        });
        Schema::connection('mysql')->create('site_builder_workflows', function (Blueprint $table): void {
            $table->id();
            $table->string('document_key', 100)->unique();
            $table->unsignedInteger('version');
            $table->string('state', 20);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('requested_by', 150);
            $table->timestamp('scheduled_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->index(['state', 'scheduled_at']);
        });
        Schema::connection('mysql')->table('users', function (Blueprint $table): void {
            $table->boolean('builder_designer')->default(true);
            $table->boolean('builder_publisher')->default(true);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('mysql')->dropIfExists('site_builder_workflows');
        Schema::connection('mysql')->dropIfExists('site_builder_assets');
        Schema::connection('mysql')->table('users', function (Blueprint $table): void {
            $table->dropColumn(['builder_designer', 'builder_publisher']);
        });
    }
};
