<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContactMessageRequest;
use App\Mail\ContactMessageMail;
use App\Models\ContactMessage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

class ContactMessageController extends Controller
{
    public function store(StoreContactMessageRequest $request): RedirectResponse
    {
        $data = $request->safe()->except(['privacy_acknowledged', 'website']);
        $message = ContactMessage::create($data + [
            'reference' => (string) Str::uuid(),
            'privacy_acknowledged_at' => now(),
        ]);

        if (filled(config('services_site.contact_email'))) {
            try {
                Mail::to(config('services_site.contact_email'))->send(new ContactMessageMail($message));
            } catch (Throwable $exception) {
                Log::error('Contact message notification could not be sent.', ['reference' => $message->reference, 'exception' => $exception]);
            }
        }

        return redirect()->route('services.contact')->with('contact_reference', $message->reference);
    }

    public function index(Request $request): View
    {
        $filters = $request->validate(['status' => ['nullable', Rule::in(['new', 'replied', 'closed'])]]);
        $messages = ContactMessage::query()->latest()
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->paginate(20)->withQueryString();

        return view('services.staff.contacts', compact('messages'));
    }

    public function show(ContactMessage $contactMessage): View
    {
        return view('services.staff.contact', compact('contactMessage'));
    }

    public function update(Request $request, ContactMessage $contactMessage): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['new', 'replied', 'closed'])],
            'staff_notes' => ['nullable', 'string', 'max:5000'],
        ]);
        $contactMessage->update($data);

        return back()->with('message', 'Contact message updated.');
    }
}
