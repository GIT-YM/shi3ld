<?php

namespace Tests\Unit;

use App\Mail\ServiceEnquiryMail;
use App\Models\ServiceEnquiry;
use PHPUnit\Framework\TestCase;

class ExampleTest extends TestCase
{
    public function test_notification_subjects_use_the_generated_reference(): void
    {
        $enquiry = new ServiceEnquiry(['reference' => 'ac67f375-87ed-4b76-b152-48035cfd2920']);

        $this->assertSame('Your Shi3ld enquiry: '.$enquiry->reference, (new ServiceEnquiryMail($enquiry))->envelope()->subject);
        $this->assertSame('New cleaning enquiry: '.$enquiry->reference, (new ServiceEnquiryMail($enquiry, true))->envelope()->subject);
    }
}
