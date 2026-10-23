<?php

namespace App\Console\Commands;

use App\Mail\ServiceEnquiryMail;
use App\Models\ServiceEnquiry;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

class DeliverServiceEnquiryNotifications extends Command
{
    private const MAX_ATTEMPTS = 5;

    private const BACKOFF_MINUTES = [1, 5, 15, 60];

    protected $signature = 'shi3ld:deliver-enquiries {--limit=50}';

    protected $description = 'Deliver due enquiry notifications with bounded retries and backoff';

    public function handle(): int
    {
        $lock = Cache::lock('services-enquiry-notifications', 600);
        if (! $lock->get()) {
            return self::SUCCESS;
        }
        try {
            $deadline = microtime(true) + 240;
            $limit = max(1, min(200, (int) $this->option('limit')));
            foreach (['customer', 'staff'] as $channel) {
                ServiceEnquiry::whereNull($channel.'_notified_at')
                    ->whereNull($channel.'_notification_failed_at')
                    ->where($channel.'_notification_attempts', '>=', self::MAX_ATTEMPTS)
                    ->update([$channel.'_notification_failed_at' => now(), $channel.'_notification_next_attempt_at' => null]);
            }
            $enquiries = ServiceEnquiry::where(function (Builder $query): void {
                $query->where(fn (Builder $query) => $this->due($query, 'customer'))
                    ->orWhere(fn (Builder $query) => $this->due($query, 'staff'));
            })->oldest('updated_at')->orderBy('id')->limit($limit)->get();
            foreach ($enquiries as $enquiry) {
                foreach (['customer' => false, 'staff' => true] as $channel => $internal) {
                    if (microtime(true) >= $deadline) {
                        break 2;
                    }
                    $sent = $channel.'_notified_at';
                    $attempts = $channel.'_notification_attempts';
                    $next = $channel.'_notification_next_attempt_at';
                    $failed = $channel.'_notification_failed_at';
                    if ($enquiry->{$sent} || $enquiry->{$failed} || $enquiry->{$attempts} >= self::MAX_ATTEMPTS
                        || $enquiry->{$next}?->isFuture()) {
                        continue;
                    }
                    $enquiry->{$attempts}++;
                    $enquiry->{$next} = now()->addMinutes(self::BACKOFF_MINUTES[min($enquiry->{$attempts} - 1, 3)]);
                    $enquiry->save();
                    try {
                        $recipient = $internal ? config('shi3ld.enquiries_email') : $enquiry->email;
                        Mail::to($recipient)->sendNow(new ServiceEnquiryMail($enquiry, $internal));
                        $enquiry->{$sent} = now();
                        $enquiry->{$next} = null;
                    } catch (\Throwable $error) {
                        report($error);
                        if ($enquiry->{$attempts} >= self::MAX_ATTEMPTS) {
                            $enquiry->{$failed} = now();
                            $enquiry->{$next} = null;
                        }
                        $this->warn('Notification delivery failed for enquiry '.$enquiry->reference);
                    }
                    $enquiry->save();
                }
            }
        } finally {
            $lock->release();
        }

        return self::SUCCESS;
    }

    private function due(Builder $query, string $channel): void
    {
        $query->whereNull($channel.'_notified_at')
            ->whereNull($channel.'_notification_failed_at')
            ->where($channel.'_notification_attempts', '<', self::MAX_ATTEMPTS)
            ->where(function (Builder $query) use ($channel): void {
                $query->whereNull($channel.'_notification_next_attempt_at')
                    ->orWhere($channel.'_notification_next_attempt_at', '<=', now());
            });
    }
}
