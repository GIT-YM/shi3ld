<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'mysql';

    public function up(): void
    {
        Schema::connection('mysql')->table('service_enquiries', function (Blueprint $table) {
            $table->string('property_type', 80)->nullable();
            $table->string('approximate_size', 100)->nullable();
            $table->unsignedSmallInteger('rooms')->nullable();
            $table->text('staff_notes')->nullable();
            $table->timestamp('customer_notified_at')->nullable()->index();
            $table->timestamp('staff_notified_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::connection('mysql')->table('service_enquiries', function (Blueprint $table) {
            $table->dropColumn(['property_type', 'approximate_size', 'rooms', 'staff_notes', 'customer_notified_at', 'staff_notified_at']);
        });
    }
};
