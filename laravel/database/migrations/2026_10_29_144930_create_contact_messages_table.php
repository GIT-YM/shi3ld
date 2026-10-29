<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'mysql';

    public function up(): void
    {
        Schema::connection('mysql')->create('contact_messages', function (Blueprint $table): void {
            $table->id();
            $table->uuid('reference')->unique();
            $table->string('name', 120);
            $table->string('email', 255);
            $table->string('phone', 30)->nullable();
            $table->string('subject', 160);
            $table->text('message');
            $table->timestamp('privacy_acknowledged_at');
            $table->string('status', 20)->default('new')->index();
            $table->text('staff_notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('mysql')->dropIfExists('contact_messages');
    }
};
