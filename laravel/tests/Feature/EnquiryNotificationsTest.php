<?php

namespace Tests\Feature;

use App\Mail\ServiceEnquiryMail;
use App\Models\ServiceEnquiry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\PendingMail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class EnquiryNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_notifications_are_sent_once_and_tracked_independently(): void
    {
        Mail::fake();
        $enquiry = ServiceEnquiry::factory()->create();
        $this->artisan('shi3ld:deliver-enquiries')->assertSuccessful();
        $this->assertNotNull($enquiry->fresh()->customer_notified_at);
        $this->assertNotNull($enquiry->fresh()->staff_notified_at);
        $this->assertSame(1, $enquiry->fresh()->customer_notification_attempts);
        Mail::assertSent(ServiceEnquiryMail::class, fn ($mail) => ! $mail->internal && $mail->hasTo($enquiry->email));
        Mail::assertSent(ServiceEnquiryMail::class, fn ($mail) => $mail->internal && $mail->hasTo(config('shi3ld.enquiries_email')));
        $this->artisan('shi3ld:deliver-enquiries')->assertSuccessful();
        Mail::assertSentCount(2);
    }

    public function test_failure_backs_off_and_stops_after_five_attempts(): void
    {
        $this->travelTo(now()->startOfSecond());
        $enquiry = ServiceEnquiry::factory()->create(['staff_notified_at' => now()]);
        $pending = Mockery::mock(PendingMail::class);
        $pending->shouldReceive('sendNow')->times(5)->andThrow(new RuntimeException('Simulated SMTP failure'));
        Mail::shouldReceive('to')->with($enquiry->email)->times(5)->andReturn($pending);
        foreach ([1, 5, 15, 60] as $attempt => $minutes) {
            $this->artisan('shi3ld:deliver-enquiries')->assertSuccessful();
            $enquiry->refresh();
            $this->assertSame($attempt + 1, $enquiry->customer_notification_attempts);
            $this->assertTrue($enquiry->customer_notification_next_attempt_at->equalTo(now()->addMinutes($minutes)));
            $this->artisan('shi3ld:deliver-enquiries')->assertSuccessful();
            $this->assertSame($attempt + 1, $enquiry->fresh()->customer_notification_attempts);
            $this->travel($minutes)->minutes();
        }
        $this->artisan('shi3ld:deliver-enquiries')->assertSuccessful();
        $enquiry->refresh();
        $this->assertSame(5, $enquiry->customer_notification_attempts);
        $this->assertNotNull($enquiry->customer_notification_failed_at);
        $this->assertNull($enquiry->customer_notification_next_attempt_at);
        $this->travel(1)->days();
        $this->artisan('shi3ld:deliver-enquiries')->assertSuccessful();
        $this->assertSame(5, $enquiry->fresh()->customer_notification_attempts);
    }

    public function test_due_retry_can_succeed_without_resending_staff_notification(): void
    {
        Mail::fake();
        $enquiry = ServiceEnquiry::factory()->create([
            'customer_notification_attempts' => 1, 'customer_notification_next_attempt_at' => now()->subMinute(),
            'staff_notified_at' => now(), 'staff_notification_attempts' => 1,
        ]);
        $this->artisan('shi3ld:deliver-enquiries')->assertSuccessful();
        $this->assertNotNull($enquiry->fresh()->customer_notified_at);
        $this->assertNull($enquiry->fresh()->customer_notification_next_attempt_at);
        $this->assertSame(2, $enquiry->fresh()->customer_notification_attempts);
        Mail::assertSentCount(1);
    }

    public function test_customer_failure_does_not_block_staff_delivery(): void
    {
        $enquiry = ServiceEnquiry::factory()->create();
        $customer = Mockery::mock(PendingMail::class);
        $customer->shouldReceive('sendNow')->once()->andThrow(new RuntimeException('Simulated customer failure'));
        $staff = Mockery::mock(PendingMail::class);
        $staff->shouldReceive('sendNow')->once();
        Mail::shouldReceive('to')->with($enquiry->email)->once()->andReturn($customer);
        Mail::shouldReceive('to')->with(config('shi3ld.enquiries_email'))->once()->andReturn($staff);
        $this->artisan('shi3ld:deliver-enquiries')->assertSuccessful();
        $this->assertNull($enquiry->fresh()->customer_notified_at);
        $this->assertNotNull($enquiry->fresh()->staff_notified_at);
    }

    public function test_future_retry_does_not_consume_batch_limit(): void
    {
        Mail::fake();
        $future = ServiceEnquiry::factory()->create([
            'customer_notification_next_attempt_at' => now()->addHour(), 'staff_notified_at' => now(),
        ]);
        $due = ServiceEnquiry::factory()->create(['staff_notified_at' => now()]);
        $this->artisan('shi3ld:deliver-enquiries', ['--limit' => 1])->assertSuccessful();
        $this->assertNull($future->fresh()->customer_notified_at);
        $this->assertNotNull($due->fresh()->customer_notified_at);
        Mail::assertSentCount(1);
    }

    public function test_crash_after_final_attempt_is_flagged_without_further_delivery(): void
    {
        Mail::fake();
        $enquiry = ServiceEnquiry::factory()->create(['customer_notification_attempts' => 5, 'staff_notified_at' => now()]);
        $this->artisan('shi3ld:deliver-enquiries')->assertSuccessful();
        $this->assertNotNull($enquiry->fresh()->customer_notification_failed_at);
        Mail::assertNothingSent();
    }

    public function test_an_existing_delivery_lock_prevents_duplicate_processing(): void
    {
        Mail::fake();
        ServiceEnquiry::factory()->create();
        $lock = Cache::lock('services-enquiry-notifications', 600);
        $this->assertTrue($lock->get());
        try {
            $this->artisan('shi3ld:deliver-enquiries')->assertSuccessful();
            Mail::assertNothingSent();
        } finally {
            $lock->release();
        }
    }

    public function test_retry_migration_can_roll_back_and_be_reapplied(): void
    {
        $migration = require database_path('migrations/2026_10_14_063527_add_notification_retry_tracking_to_service_enquiries_table.php');
        $migration->down();
        $this->assertFalse(Schema::connection('mysql')->hasColumn('service_enquiries', 'customer_notification_attempts'));
        $migration->up();
        $this->assertTrue(Schema::connection('mysql')->hasColumn('service_enquiries', 'customer_notification_attempts'));
    }
}
