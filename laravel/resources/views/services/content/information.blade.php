@php
$titles=['how-it-works'=>'How it works','areas'=>'Service areas','faqs'=>'Common questions','contact'=>'Let’s talk about your space','privacy'=>'Privacy information','cookies'=>'Cookie information','terms'=>'Service terms','gallery'=>'Spaces, thoughtfully considered'];
@endphp
<div class="container breadcrumb"><a href="{{ route('services.home') }}">Services</a><span aria-hidden="true">/</span><span>{{ $titles[$page] }}</span></div>
<section class="container information-hero"><p class="eyebrow">SHI3LD ALLIANCE / CLEANING SERVICES</p><h1>{{ $titles[$page] }}.</h1></section>
<section class="container information-body section-topless">
@switch($page)

@case('how-it-works')
<div class="prose"><p class="lead">A straightforward journey from first enquiry to an agreed cleaning plan.</p>@foreach([['Send your enquiry','Choose domestic, commercial or care-home cleaning. Add your postcode, the areas to be cleaned and your preferred frequency. You’ll receive an enquiry reference.'],['Discuss your requirements','The team will clarify the site, tasks and practical arrangements. Commercial and care-home enquiries may need a site discussion before quoting.'],['Review your quotation','Check the scope, price, exclusions and proposed timing. Ask about any extra tasks before agreeing the work.'],['Confirm your arrangements','A booking is confirmed separately after the details have been agreed. Keep your reference for any follow-up questions.']] as $index=>$step)<div class="process-row"><span>{{ sprintf('%02d', $index+1) }}</span><div><h2>{{ $step[0] }}</h2><p>{{ $step[1] }}</p></div></div>@endforeach<a class="button" href="{{ route('quote.create') }}">Start your enquiry ↗</a></div>
@break
@case('areas')
<div class="split-section"><div><h2>Let’s start<br>with your postcode.</h2><p class="lead">Coverage depends on your location and the type of cleaning you need. Send your postcode so the team can confirm availability.</p><p>We don’t confirm a service area or date until your requirements have been reviewed. Commercial and care-home enquiries can also include more than one site.</p></div><div data-editor-widget="area-enquiry"></div></div>
@break
@case('faqs')
<div class="split-section"><div><h2>A few details,<br>made clearer.</h2><p>From your first enquiry to preparing your space, here are answers to some common questions.</p><a class="text-link" href="{{ route('services.contact') }}">Ask another question ↗</a></div><div class="faq-list">@foreach(config('services_site.faqs') as $faq)<details><summary>{{ $faq['question'] }}</summary><p>{{ $faq['answer'] }}</p></details>@endforeach</div></div>
@break
@case('contact')
<div class="split-section contact-layout"><div><h2>Tell us what<br>you have in mind.</h2><p class="lead">For a general question, a follow-up or help with an existing enquiry, send the team a message here.</p>@if(config('services_site.contact_email'))<p><a href="mailto:{{ config('services_site.contact_email') }}">{{ config('services_site.contact_email') }}</a></p>@endif @if(config('services_site.contact_phone'))<p>{{ config('services_site.contact_phone') }}</p>@endif<p>Looking for a cleaning price? Use the separate <a href="{{ route('quote.create') }}">request a quote form</a>.</p></div><div>
@if(session('contact_reference'))
<div class="confirmation" role="status" tabindex="-1"><span class="round-icon" aria-hidden="true">✓</span><h2>Your message is with us.</h2><p>The team will review it and follow up using the contact details you provided.</p><p>Your reference<br><strong class="reference">{{ session('contact_reference') }}</strong></p><a class="button" href="{{ route('services.home') }}">Back to services ↗</a></div>
@else
@include('services.feedback')
<form class="form-panel contact-form" method="post" action="{{ route('services.contact.submit') }}">
    @csrf
    <div class="trap" aria-hidden="true"><label for="contact_website">Website</label><input id="contact_website" name="website" tabindex="-1" autocomplete="off"></div>
    <div class="field"><label for="contact_name">Your name <span class="required">*</span></label><input id="contact_name" name="name" autocomplete="name" maxlength="120" required value="{{ old('name') }}"></div>
    <div class="field"><label for="contact_email">Email address <span class="required">*</span></label><input id="contact_email" name="email" type="email" autocomplete="email" maxlength="255" required value="{{ old('email') }}"></div>
    <div class="field"><label for="contact_phone">Telephone <span class="optional">(optional)</span></label><input id="contact_phone" name="phone" type="tel" autocomplete="tel" maxlength="30" value="{{ old('phone') }}"></div>
    <div class="field"><label for="contact_subject">Subject <span class="required">*</span></label><input id="contact_subject" name="subject" maxlength="160" required value="{{ old('subject') }}"></div>
    <div class="field"><label for="contact_message">Your message <span class="required">*</span></label><textarea id="contact_message" name="message" rows="6" minlength="10" maxlength="3000" required>{{ old('message') }}</textarea><p class="hint">For a cleaning price, use the request a quote form. Please leave out health information, resident details and access codes.</p></div>
    <div class="field check"><input id="contact_privacy_acknowledged" name="privacy_acknowledged" type="checkbox" value="1" required @checked(old('privacy_acknowledged'))><label for="contact_privacy_acknowledged">I understand that Shi3ld Alliance will use these details to respond to my message, as explained in the <a href="{{ route('services.privacy') }}">privacy information</a>. <span class="required">*</span></label></div>
    <button class="button" type="submit">Send message <span aria-hidden="true">↗</span></button><p class="hint">* Required field.</p>
</form>
@endif
</div></div>
@break
@case('gallery')
<p class="lead">Cleaning for homes, workplaces and shared spaces.</p><div class="gallery-grid">@foreach([['person-holding-basket-full-cleaning-supplies-web-medium.webp','Domestic cleaning'],['person-cleaning-floor-mop-2-web-medium.webp','Workplace cleaning'],['yellow-mop-bucket-bucket-trolley-web-medium.webp','Care-setting cleaning']] as $image)<figure><img src="{{ asset('services/assets/alliance-home/'.$image[0]) }}" alt="{{ $image[1] }}" width="1200" height="800" loading="lazy"><figcaption>{{ $image[1] }}</figcaption></figure>@endforeach</div><div class="info-card"><h2>Find your cleaning plan</h2><p>Explore our services or tell us about your space so we can discuss a cleaning plan around your needs.</p><a class="text-link" href="{{ route('quote.create') }}">Discuss your requirements ↗</a></div>
@break
@case('privacy')
<div class="prose"><div class="notice">Development draft. Final business contact details, retention periods and privacy wording need approval before public launch.</div><h2>Your enquiry details</h2><p>The quote form collects your contact details, postcode, service requirements and preferred schedule. The separate contact form collects your name, email, optional telephone number, subject and message. Both forms record your privacy acknowledgement and issue a reference.</p><h2>How this preview uses them</h2><p>Quote requests and contact messages are stored separately in the services application and can be reviewed by authorised services staff. Contact messages may also be emailed to the configured services contact address.</p><h2>What to leave out</h2><p>Do not include health information, details about care-home residents, access codes or other sensitive personal data.</p><h2>Your questions</h2><p>Use the <a href="{{ route('services.contact') }}">contact page</a> to raise a question about your enquiry. A production privacy notice will identify the data controller, contact channels, lawful bases, recipients, retention periods and rights process.</p></div>
@break
@case('cookies')
<div class="prose"><h2>Keeping the service working</h2><p>This preview uses a session cookie to support form security, validation messages and staff sign-in. The services cookie is scoped to the services area.</p><h2>Analytics and marketing</h2><p>No analytics or advertising scripts are included in this services preview. No optional cookies are used by these service pages.</p><h2>Managing cookies</h2><p>You can manage cookies through your browser settings. Blocking the session cookie can stop enquiry submissions and staff sign-in from working.</p><p>The accessibility shop has a separate application and cookie configuration.</p></div>
@break
@case('terms')
<div class="prose"><div class="notice">Development draft. Final service terms require business approval before bookings or paid services are introduced.</div><h2>Enquiries and quotations</h2><p>Sending an enquiry does not confirm a booking, a price or availability. The service scope, timing, price and arrangements need to be agreed separately.</p><h2>Planning the work</h2><p>Before confirming work, discuss access, site conditions, equipment, products, suitable surfaces, additional tasks and exclusions with the team.</p><h2>Changes and cancellations</h2><p>Cancellation, rescheduling, access arrangements and recurring service terms will be provided as part of the agreed service arrangements.</p><h2>Questions or concerns</h2><p>Use the <a href="{{ route('services.contact') }}">contact page</a> and include your enquiry reference.</p></div>
@break
@endswitch
</section>
