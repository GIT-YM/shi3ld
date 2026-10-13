<?php

namespace App\Mail;

use App\Models\ServiceEnquiry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ServiceEnquiryMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public ServiceEnquiry $enquiry, public bool $internal = false) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: ($this->internal ? 'New cleaning enquiry: ' : 'Your Shi3ld enquiry: ').$this->enquiry->reference);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.service-enquiry');
    }
}
