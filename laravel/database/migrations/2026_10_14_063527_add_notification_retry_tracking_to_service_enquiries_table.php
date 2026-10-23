<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'mysql';

    public function up(): void
    {
        Schema::connection('mysql')->table('service_enquiries', function (Blueprint $table): void {
            foreach (['customer', 'staff'] as $channel) {
                $table->unsignedSmallInteger($channel.'_notification_attempts')->default(0);
                $table->timestamp($channel.'_notification_next_attempt_at')->nullable()->index();
                $table->timestamp($channel.'_notification_failed_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::connection('mysql')->table('service_enquiries', function (Blueprint $table): void {
            foreach (['customer', 'staff'] as $channel) {
                $table->dropIndex([$channel.'_notification_next_attempt_at']);
                $table->dropColumn([$channel.'_notification_attempts', $channel.'_notification_next_attempt_at', $channel.'_notification_failed_at']);
            }
        });
    }
};
