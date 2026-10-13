<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'mysql';

    public function up(): void
    {
        Schema::connection('mysql')->create('service_pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('category', 30)->index();
            $table->string('title', 160);
            $table->string('eyebrow', 120);
            $table->string('summary', 500);
            $table->text('description');
            $table->json('tasks');
            $table->json('extras');
            $table->text('preparation');
            $table->string('frequency', 300);
            $table->json('faqs');
            $table->boolean('published')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('mysql')->dropIfExists('service_pages');
    }
};
