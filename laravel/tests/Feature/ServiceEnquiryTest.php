<?php

namespace Tests\Feature;

use App\Mail\ServiceEnquiryMail;
use App\Models\ServiceEnquiry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ServiceEnquiryTest extends TestCase
{
    use RefreshDatabase;

    private function submission(array $overrides = []): array
    {
        return array_replace([
            'service_type' => 'domestic', 'name' => 'Sample Customer', 'email' => 'customer@example.test',
            'postcode' => 'sw1a 1aa', 'property_type' => 'house', 'frequency' => 'weekly',
            'preferred_contact' => 'email', 'requirements' => 'Please clean the kitchen and living room.',
            'privacy_acknowledged' => '1', 'website' => '',
        ], $overrides);
    }

    public function test_valid_quote_is_saved_with_a_reference_and_protected_fields(): void
    {
        Mail::fake();
        $this->post('/services/quote', $this->submission(['status' => 'closed', 'staff_notes' => 'Injected']))
            ->assertRedirect('/services/quote')->assertSessionHas('enquiry_reference');
        $enquiry = ServiceEnquiry::sole();
        $this->assertSame('SW1A 1AA', $enquiry->postcode);
        $this->assertSame('new', $enquiry->status);
        $this->assertNull($enquiry->staff_notes);
        $this->assertNotNull($enquiry->privacy_acknowledged_at);
        $this->assertSame(session('enquiry_reference'), $enquiry->reference);
        Mail::assertNothingSent();
    }

    public function test_invalid_quote_is_rejected_without_creating_an_enquiry(): void
    {
        $this->post('/services/quote', $this->submission(['email' => 'invalid', 'postcode' => 'invalid', 'privacy_acknowledged' => '0']))
            ->assertSessionHasErrors(['email', 'postcode', 'privacy_acknowledged']);
        $this->assertSame(0, ServiceEnquiry::count());
    }

    public function test_honeypot_submission_is_rejected(): void
    {
        $this->post('/services/quote', $this->submission(['website' => 'spam.example']))
            ->assertSessionHasErrors('website');
        $this->assertSame(0, ServiceEnquiry::count());
    }

    public function test_commercial_quote_requires_organisation_and_site_details(): void
    {
        $this->post('/services/quote', $this->submission(['service_type' => 'commercial']))
            ->assertSessionHasErrors(['organisation', 'site_details']);
    }

    public function test_phone_preference_requires_a_phone_number(): void
    {
        $this->post('/services/quote', $this->submission(['preferred_contact' => 'phone']))
            ->assertSessionHasErrors('phone');
    }

    public function test_quote_submission_is_rate_limited(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/services/quote', $this->submission())->assertRedirect();
        }
        $this->post('/services/quote', $this->submission())->assertStatus(429);
        $this->assertSame(5, ServiceEnquiry::count());
    }

    public function test_acknowledgement_does_not_include_submitted_free_text(): void
    {
        $enquiry = ServiceEnquiry::factory()->create(['name' => 'Untrusted name marker', 'requirements' => 'Untrusted requirements marker']);
        $html = (new ServiceEnquiryMail($enquiry))->render();
        $this->assertStringNotContainsString('Untrusted name marker', $html);
        $this->assertStringNotContainsString('Untrusted requirements marker', $html);
        $this->assertStringContainsString($enquiry->reference, $html);
        $this->assertStringContainsString('Untrusted requirements marker', (new ServiceEnquiryMail($enquiry, true))->render());
    }

    public function test_reporting_connection_is_not_registered(): void
    {
        $this->assertNull(config('database.connections.magento_reporting'));
    }
}
