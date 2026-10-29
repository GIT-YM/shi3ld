<?php

namespace App\Mail;

use App\Models\ContactMessage as ContactMessageModel;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class ContactMessageMail extends Mailable
{
    public function __construct(public ContactMessageModel $contactMessage) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'New contact message: '.$this->contactMessage->reference);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.contact-message');
    }
}
