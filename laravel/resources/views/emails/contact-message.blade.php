<h1>New contact message</h1>
<p>Reference: {{ $contactMessage->reference }}</p>
<p>From: {{ $contactMessage->name }}<br>Email: {{ $contactMessage->email }}<br>Telephone: {{ $contactMessage->phone ?: 'Not provided' }}</p>
<p>Subject: {{ $contactMessage->subject }}</p>
<p>{{ $contactMessage->message }}</p>
