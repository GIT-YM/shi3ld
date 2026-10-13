<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'mysql';

    public function up(): void
    {
        Schema::connection('mysql')->create('service_enquiries', function (Blueprint $table) {
            $table->id();
            $table->uuid('reference')->unique();
            $table->string('service_type', 30);
            $table->string('name', 120);
            $table->string('email');
            $table->string('phone', 30)->nullable();
            $table->string('postcode', 12);
            $table->string('organisation', 180)->nullable();
            $table->string('site_details', 500)->nullable();
            $table->string('frequency', 30);
            $table->string('preferred_contact', 10);
            $table->date('preferred_start_date')->nullable();
            $table->text('requirements');
            $table->timestamp('privacy_acknowledged_at');
            $table->string('status', 30)->default('new')->index();
            $table->unsignedBigInteger('magento_customer_id')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('mysql')->dropIfExists('service_enquiries');
    }
};
