<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class BuilderWorkflow
{
    public function __construct(private ContentDocuments $documents) {}

    public function get(string $key): ?array
    {
        $row = DB::connection('mysql')->table('site_builder_workflows')->where('document_key', $key)->first();

        return $row ? (array) $row : null;
    }

    public function request(string $key, int $version, User $actor, ?string $when = null): array
    {
        $state = $this->documents->get($key);
        if (! $version || $state['version'] !== $version) {
            throw new \InvalidArgumentException('Save the current draft before submitting it.');
        }
        $scheduled = null;
        if ($when !== null) {
            if (! preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(?::\d{2})?Z?$/D', $when)) {
                throw new \InvalidArgumentException('Enter a UTC publication date and time.');
            }
            $time = strtotime($when.(str_ends_with($when, 'Z') ? '' : ' UTC'));
            if (! $time || $time <= time()) {
                throw new \InvalidArgumentException('Choose a future publication time.');
            }
            $scheduled = gmdate('Y-m-d H:i:s', $time);
        }
        DB::connection('mysql')->table('site_builder_workflows')->updateOrInsert(['document_key' => $key],
            ['version' => $version, 'state' => $when ? 'scheduled' : 'review', 'actor_id' => $actor->id, 'requested_by' => mb_substr($actor->email, 0, 150),
                'scheduled_at' => $scheduled, 'note' => null, 'updated_at' => now(), 'created_at' => now()]);

        return $this->get($key);
    }

    public function cancel(string $key): void
    {
        DB::connection('mysql')->table('site_builder_workflows')->where('document_key', $key)->update(['state' => 'cancelled', 'note' => 'Cancelled by an editor.', 'updated_at' => now()]);
    }

    public function pending(): array
    {
        return DB::connection('mysql')->table('site_builder_workflows')->whereIn('state', ['review', 'scheduled'])->orderByDesc('updated_at')->limit(100)->get()
            ->map(fn (object $row) => (array) $row + ['url' => route('services.staff.visual', $row->document_key)])->all();
    }

    public function run(): void
    {
        Cache::lock('shi3ld-builder-scheduler', 55)->get(function (): void {
            foreach (DB::connection('mysql')->table('site_builder_workflows')->where('state', 'scheduled')->where('scheduled_at', '<=', now())->limit(50)->get() as $job) {
                try {
                    $actor = User::find($job->actor_id);
                    if (! $actor?->services_staff || ! $actor->builder_publisher) {
                        throw new \InvalidArgumentException('The scheduling user no longer has publication permission.');
                    }
                    $this->documents->publish($job->document_key, $job->version, 'Scheduled: '.$actor->email);
                    DB::connection('mysql')->table('site_builder_workflows')->where('id', $job->id)->update(['state' => 'published', 'note' => null, 'updated_at' => now()]);
                } catch (\Throwable $exception) {
                    report($exception);
                    DB::connection('mysql')->table('site_builder_workflows')->where('id', $job->id)->update(['state' => 'failed', 'note' => mb_substr($exception->getMessage(), 0, 2000), 'updated_at' => now()]);
                }
            }
        });
    }
}
