<?php

namespace Tests\Feature;

use App\Models\ServiceEnquiry;
use App\Models\ServicePage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServicesStaffTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_staff_login(): void
    {
        $this->get('/services/staff')->assertRedirect('/services/staff/login');
    }

    public function test_staff_can_login_and_logout(): void
    {
        $staff = User::factory()->create(['services_staff' => true]);
        $this->post('/services/staff/login', ['email' => $staff->email, 'password' => 'password'])->assertRedirect('/services/staff');
        $this->assertAuthenticatedAs($staff);
        $this->get('/services/staff')->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->post('/services/staff/logout')->assertRedirect('/services/staff/login');
        $this->assertGuest();
    }

    public function test_non_staff_cannot_login_through_staff_form(): void
    {
        $user = User::factory()->create();
        $this->post('/services/staff/login', ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_authenticated_non_staff_is_forbidden_from_staff_routes(): void
    {
        $user = User::factory()->create();
        $enquiry = ServiceEnquiry::factory()->create();
        $page = ServicePage::factory()->create();
        $this->actingAs($user);
        foreach (['/services/staff', '/services/staff/export', '/services/staff/content', '/services/staff/enquiries/'.$enquiry->id] as $url) {
            $this->get($url)->assertForbidden();
        }
        $this->patch('/services/staff/enquiries/'.$enquiry->id, ['status' => 'closed'])->assertForbidden();
        $this->patch('/services/staff/content/'.$page->id, [])->assertForbidden();
    }

    public function test_staff_can_update_enquiry_status_and_notes(): void
    {
        $enquiry = ServiceEnquiry::factory()->create();
        $this->actingAs(User::factory()->create(['services_staff' => true]))
            ->patch('/services/staff/enquiries/'.$enquiry->id, ['status' => 'quoted', 'staff_notes' => 'Discussed requirements.'])
            ->assertRedirect();
        $this->assertSame('quoted', $enquiry->fresh()->status);
        $this->assertSame('Discussed requirements.', $enquiry->fresh()->staff_notes);
    }

    public function test_invalid_enquiry_status_is_rejected(): void
    {
        $enquiry = ServiceEnquiry::factory()->create();
        $this->actingAs(User::factory()->create(['services_staff' => true]))
            ->patch('/services/staff/enquiries/'.$enquiry->id, ['status' => 'unknown'])->assertSessionHasErrors('status');
        $this->assertSame('new', $enquiry->fresh()->status);
    }

    public function test_csv_export_neutralises_formula_prefixes(): void
    {
        ServiceEnquiry::factory()->create(['name' => '=SUM(1,2)', 'phone' => '+441234567890', 'organisation' => '@formula', 'requirements' => " \t-danger"]);
        $response = $this->actingAs(User::factory()->create(['services_staff' => true]))->get('/services/staff/export');
        $response->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $csv = $response->streamedContent();
        $this->assertStringContainsString("'=SUM(1,2)", $csv);
        $this->assertStringContainsString("'+441234567890", $csv);
        $this->assertStringContainsString("'@formula", $csv);
        $this->assertStringContainsString("' \t-danger", $csv);
    }

    public function test_staff_can_edit_unpublish_and_publish_a_service_page(): void
    {
        $page = ServicePage::factory()->create();
        $data = [
            'title' => 'Updated cleaning page', 'eyebrow' => 'Domestic cleaning', 'summary' => 'An updated summary.',
            'description' => 'An updated description.', 'tasks_text' => "Kitchen surfaces\nBathroom surfaces", 'extras_text' => 'Oven cleaning',
            'preparation' => 'Agree access.', 'frequency' => 'Weekly',
            'faqs' => [['question' => 'When?', 'answer' => 'By agreement.']], 'published' => false,
        ];
        $this->actingAs(User::factory()->create(['services_staff' => true]))->patch('/services/staff/content/'.$page->id, $data)->assertRedirect();
        $this->assertSame('Updated cleaning page', $page->fresh()->title);
        $this->assertSame(['Kitchen surfaces', 'Bathroom surfaces'], $page->fresh()->tasks);
        $this->get('/services/'.$page->slug)->assertNotFound();
        $this->get('/services/sitemap.xml')->assertDontSee('/services/'.$page->slug);
        $this->patch('/services/staff/content/'.$page->id, array_replace($data, ['published' => true]))->assertRedirect();
        $this->get('/services/'.$page->slug)->assertOk()->assertSee('Updated cleaning page');
        $this->get('/services/sitemap.xml')->assertSee('/services/'.$page->slug);
    }

    public function test_search_treats_percent_underscore_and_escape_character_literally(): void
    {
        $special = ServiceEnquiry::factory()->create(['name' => 'Literal %_! customer']);
        ServiceEnquiry::factory()->create(['name' => 'Ordinary customer']);
        $this->actingAs(User::factory()->create(['services_staff' => true]))
            ->get('/services/staff?search='.urlencode('%_!'))->assertOk()
            ->assertSee($special->reference)->assertDontSee('Ordinary customer');
    }

    public function test_failed_notifications_are_flagged_for_staff(): void
    {
        $enquiry = ServiceEnquiry::factory()->create(['customer_notification_failed_at' => now(), 'customer_notification_attempts' => 5]);
        $this->actingAs(User::factory()->create(['services_staff' => true]))
            ->get('/services/staff')->assertOk()->assertSee('failed email delivery');
        $this->get('/services/staff/enquiries/'.$enquiry->id)->assertOk()->assertSee('staff attention required');
    }
}
