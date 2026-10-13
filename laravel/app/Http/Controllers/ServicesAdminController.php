<?php

namespace App\Http\Controllers;

use App\Http\Requests\ServicesStaffLoginRequest;
use App\Http\Requests\StoreServicePageRequest;
use App\Http\Requests\UpdateServiceEnquiryRequest;
use App\Models\ServiceEnquiry;
use App\Models\ServicePage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ServicesAdminController extends Controller
{
    public function login(): View
    {
        return view('services.staff.login');
    }

    public function authenticate(ServicesStaffLoginRequest $request): RedirectResponse
    {
        $credentials = $request->validated();
        $credentials['services_staff'] = true;
        if (! Auth::attempt($credentials)) {
            throw ValidationException::withMessages(['email' => 'The sign-in details could not be verified.']);
        }
        $request->session()->regenerate();

        return redirect()->route('services.staff.index');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', 'in:new,contacted,quoted,closed'],
            'category' => ['nullable', 'in:domestic,commercial,care_home'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);
        $query = ServiceEnquiry::query()->latest();
        $query->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status));
        $query->when($filters['category'] ?? null, fn ($query, $category) => $query->where('service_type', $category));
        $query->when($filters['search'] ?? null, function ($query, $search): void {
            $query->where(function ($query) use ($search): void {
                $query->where('reference', 'like', '%'.$search.'%')
                    ->orWhere('name', 'like', '%'.$search.'%')
                    ->orWhere('postcode', 'like', '%'.$search.'%');
            });
        });

        return view('services.staff.index', [
            'enquiries' => $query->paginate(20)->withQueryString(),
            'counts' => ServiceEnquiry::selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status'),
        ]);
    }

    public function show(ServiceEnquiry $enquiry): View
    {
        return view('services.staff.enquiry', compact('enquiry'));
    }

    public function update(UpdateServiceEnquiryRequest $request, ServiceEnquiry $enquiry): RedirectResponse
    {
        $enquiry->status = $request->validated('status');
        $enquiry->staff_notes = $request->validated('staff_notes');
        $enquiry->save();

        return back()->with('message', 'Enquiry updated.');
    }

    public function export(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            $stream = fopen('php://output', 'w');
            fputcsv($stream, ['Reference', 'Received', 'Service', 'Status', 'Name', 'Email', 'Phone', 'Postcode', 'Organisation', 'Property type', 'Approximate size', 'Rooms', 'Frequency', 'Preferred contact', 'Preferred start', 'Requirements'], escape: '');
            foreach (ServiceEnquiry::query()->lazyById(200) as $enquiry) {
                $values = [
                    $enquiry->reference, $enquiry->created_at->toIso8601String(), $enquiry->service_type,
                    $enquiry->status, $enquiry->name, $enquiry->email, $enquiry->phone, $enquiry->postcode,
                    $enquiry->organisation, $enquiry->property_type, $enquiry->approximate_size, $enquiry->rooms,
                    $enquiry->frequency, $enquiry->preferred_contact, $enquiry->preferred_start_date?->toDateString(),
                    $enquiry->requirements,
                ];
                $values = array_map(function ($value): string {
                    $value = (string) $value;

                    return preg_match('/^[\\s]*[=+@-]/u', $value) ? "'".$value : $value;
                }, $values);
                fputcsv($stream, $values, escape: '');
            }
            fclose($stream);
        }, 'shi3ld-enquiries-'.now()->format('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store, private',
        ]);
    }

    public function content(): View
    {
        return view('services.staff.content', ['pages' => ServicePage::orderBy('category')->orderBy('title')->get()]);
    }

    public function editContent(ServicePage $page): View
    {
        return view('services.staff.edit', compact('page'));
    }

    public function updateContent(StoreServicePageRequest $request, ServicePage $page): RedirectResponse
    {
        $data = $request->safe()->except(['tasks_text', 'extras_text']);
        $data['tasks'] = $this->lines($request->validated('tasks_text'));
        $data['extras'] = $this->lines($request->validated('extras_text') ?? '');
        $data['faqs'] = array_values(array_filter($data['faqs'] ?? [], fn ($faq) => filled($faq['question'] ?? null) && filled($faq['answer'] ?? null)));
        $page->update($data);

        return back()->with('message', 'Page saved.');
    }

    private function lines(string $text): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\\R/u', $text))));
    }
}
