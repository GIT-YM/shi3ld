<?php

namespace App\Http\Controllers;

use App\Models\ServiceEnquiry;
use App\Services\BlogAttribution;
use App\Services\Builder\Schema;
use App\Services\BuilderLibrary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class BuilderFormController extends Controller
{
    public function __construct(private BuilderLibrary $library, private Schema $schema, private BlogAttribution $blogAttribution) {}

    private function definition(string $key): array
    {
        $asset = $this->library->get($key);
        abort_unless($asset && ($asset['builder']['asset']['kind'] ?? '') === 'form', 404);
        $context = ['path' => '/', 'audience' => auth()->user()?->services_staff ? 'staff' : (auth()->check() ? 'customer' : 'guest')];
        // Date restrictions apply to submissions; the embedding page controls path visibility.
        $rules = $asset['builder']['conditions'] ?? [];
        $rules['include'] = '';
        $rules['exclude'] = '';
        abort_unless($this->schema->matches($rules, $context), 404);

        return $this->schema->settings($asset['builder'])['asset']['form'];
    }

    public function show(string $key): JsonResponse
    {
        return response()->json(['definition' => $this->definition($key), 'csrf' => csrf_token()])->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request, string $key): JsonResponse
    {
        $definition = $this->definition($key);
        $rules = ['name' => 'required|string|max:120', 'email' => 'required|email|max:255', 'phone' => 'nullable|string|max:30',
            'postcode' => ['required', 'string', 'max:12', 'regex:/^[A-Z]{1,2}[0-9][A-Z0-9]?\s*[0-9][A-Z]{2}$/i'],
            'requirements' => 'required|string|min:10|max:3000', 'privacy_acknowledged' => 'required|accepted', 'website' => 'nullable|max:0', 'fields' => 'nullable|array|max:30',
            'organisation' => $definition['service_type'] === 'domestic' ? 'nullable|string|max:180' : 'required|string|max:180',
            'site_details' => $definition['service_type'] === 'domestic' ? 'nullable|string|max:500' : 'required|string|max:500'];
        foreach ($definition['fields'] as $field) {
            $rule = [$field['required'] ? 'required' : 'nullable'];
            if ($field['type'] === 'checkbox') {
                $rule[] = 'accepted';
            } elseif ($field['type'] === 'number') {
                $rule[] = 'numeric';
                $rule[] = 'between:-100000000,100000000';
            } elseif ($field['type'] === 'date') {
                $rule[] = 'date_format:Y-m-d';
            } else {
                $rule[] = 'string';
                $rule[] = 'max:2000';
                if ($field['type'] === 'email') {
                    $rule[] = 'email';
                }
            }
            if ($field['type'] === 'select') {
                $rule[] = Rule::in($field['options']);
            }
            $rules['fields.'.$field['name']] = $rule;
        }
        $data = Validator::make($request->all(), $rules)->validate();
        $extra = [];
        foreach ($definition['fields'] as $field) {
            if (isset($data['fields'][$field['name']])) {
                $extra[] = $field['label'].': '.(string) $data['fields'][$field['name']];
            }
        }
        $requirements = $data['requirements'].($extra ? "\n\nAdditional form answers:\n".implode("\n", $extra) : '');
        $enquiry = DB::connection('mysql')->transaction(fn () => ServiceEnquiry::create(['reference' => (string) Str::uuid(), 'service_type' => $definition['service_type'],
            'name' => $data['name'], 'email' => $data['email'], 'phone' => $data['phone'] ?? null, 'postcode' => strtoupper(trim($data['postcode'])),
            'organisation' => $data['organisation'] ?? null, 'site_details' => $data['site_details'] ?? null, 'frequency' => 'discuss', 'preferred_contact' => 'email',
            'property_type' => 'other', 'requirements' => mb_substr($requirements, 0, 15000), 'privacy_acknowledged_at' => now()] + $this->blogAttribution->attributes($request)));
        $this->blogAttribution->queue($enquiry);

        return response()->json(['reference' => $enquiry->reference])->header('Cache-Control', 'private, no-store');
    }
}
