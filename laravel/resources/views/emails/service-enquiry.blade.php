<h1>{{ $internal ? 'New cleaning enquiry' : 'Thank you for your enquiry' }}</h1>
<p>Reference: {{ $enquiry->reference }}</p>
<p>Service: {{ str_replace('_', ' ', $enquiry->service_type) }}</p>
@if ($internal)
<p>Name: {{ $enquiry->name }}<br>Email: {{ $enquiry->email }}<br>Phone: {{ $enquiry->phone }}<br>Postcode: {{ $enquiry->postcode }}</p>
<p>Organisation: {{ $enquiry->organisation }}<br>Site: {{ $enquiry->site_details }}<br>Frequency: {{ $enquiry->frequency }}</p>
<p>Property type: {{ $enquiry->property_type }}<br>Approximate size: {{ $enquiry->approximate_size }}<br>Rooms/areas: {{ $enquiry->rooms }}<br>Preferred start: {{ $enquiry->preferred_start_date?->format('d M Y') ?? 'To discuss' }}<br>Contact preference: {{ $enquiry->preferred_contact }}</p>
<p>{{ $enquiry->requirements }}</p>
@else
<p>Our team will review your requirements and contact you to discuss availability and a quotation. This enquiry does not confirm a booking.</p>
@endif
