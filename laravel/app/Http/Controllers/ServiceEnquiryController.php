<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreServiceEnquiryRequest;
use App\Models\ServiceEnquiry;
use App\Services\BlogAttribution;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ServiceEnquiryController extends Controller
{
    public function __construct(private BlogAttribution $blogAttribution) {}

    public function create(Request $request): View
    {
        $service = $request->query('service');
        $service = in_array($service, ['domestic', 'commercial', 'care_home'], true) ? $service : '';

        $postcode = $request->query('postcode');

        return view('enquiries.create', ['selectedService' => $service, 'selectedPostcode' => is_string($postcode) ? substr($postcode, 0, 12) : '', 'blogAttribution' => $this->blogAttribution->current($request)]);
    }

    public function store(StoreServiceEnquiryRequest $request): RedirectResponse
    {
        $data = $request->safe()->except(['privacy_acknowledged', 'website']);
        $data['postcode'] = strtoupper(trim($data['postcode']));
        $data['reference'] = (string) Str::uuid();
        $data['privacy_acknowledged_at'] = now();
        $data += $this->blogAttribution->attributes($request);
        $enquiry = DB::connection('mysql')->transaction(fn () => ServiceEnquiry::create($data));
        $this->blogAttribution->queue($enquiry);

        return redirect()->route('quote.create')->with('enquiry_reference', $enquiry->reference);
    }
}
