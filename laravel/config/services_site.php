<?php

return [
    'sample_content' => env('SERVICES_SAMPLE_CONTENT', true),
    'contact_email' => env('SERVICES_CONTACT_EMAIL'),
    'contact_phone' => env('SERVICES_CONTACT_PHONE'),
    'production_url' => 'https://www.shi3ldalliance.co.uk/services',
    'statuses' => ['new' => 'New', 'contacted' => 'Contacted', 'quoted' => 'Quoted', 'closed' => 'Closed'],
    'faqs' => [
        ['question' => 'How do I get a quote?', 'answer' => 'Choose your service and send us your postcode, the areas that need cleaning and your preferred schedule. The team will discuss the requirements with you before providing a quotation.'],
        ['question' => 'Do you cover my area?', 'answer' => 'Coverage depends on the location and type of work. Include your postcode in your enquiry so the team can confirm availability.'],
        ['question' => 'Can I request a regular clean?', 'answer' => 'Yes. You can enquire about weekly, fortnightly, monthly or bespoke schedules. The agreed frequency will depend on your needs and availability.'],
        ['question' => 'Is sending an enquiry a confirmed booking?', 'answer' => 'No. Your enquiry starts a conversation. A price, scope and date need to be agreed before a booking is confirmed.'],
        ['question' => 'What about materials and cleaning products?', 'answer' => 'Tell us about any delicate surfaces or product preferences. Equipment, products and any additional requirements will be agreed as part of the quotation.'],
        ['question' => 'Can I discuss access or accessibility requirements?', 'answer' => 'Yes. Let the team know about practical access arrangements. Avoid including door codes, medical information or details about residents in the enquiry form.'],
        ['question' => 'Can you provide commercial or care-home documentation?', 'answer' => 'Ask which risk assessments, product information, workforce checks and insurance documents are available for the proposed work. Requirements will be discussed before any service is agreed.'],
        ['question' => 'How do I change my enquiry?', 'answer' => 'Use the contact form and include your enquiry reference. The team can discuss changes to the requirements or preferred schedule.'],
    ],
];
