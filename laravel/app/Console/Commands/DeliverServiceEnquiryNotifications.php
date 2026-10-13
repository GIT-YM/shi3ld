<?php

namespace App\Console\Commands;

use App\Mail\ServiceEnquiryMail;
use App\Models\ServiceEnquiry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

class DeliverServiceEnquiryNotifications extends Command
{
    protected $signature = 'shi3ld:deliver-enquiries {--limit=50}';

    protected $description = 'Deliver pending enquiry acknowledgements and staff notifications, retrying failures on the next run';

    public function handle(): int
    {
        $lock = Cache::lock('services-enquiry-notifications', 600);
        if (! $lock->get()) {
            return self::SUCCESS;
        }
        try {
            $deadline = microtime(true) + 240;
            $limit = max(1, min(200, (int) $this->option('limit')));
            $enquiries = ServiceEnquiry::where(function ($query): void {
                $query->whereNull('customer_notified_at')->orWhereNull('staff_notified_at');
            })->oldest('updated_at')->orderBy('id')->limit($limit)->get();
            foreach ($enquiries as $enquiry) {
                foreach (['customer_notified_at' => false, 'staff_notified_at' => true] as $column => $internal) {
                    if (microtime(true) >= $deadline) {
                        break 2;
                    }
                    if ($enquiry->{$column}) {
                        continue;
                    }
                    try {
                        $recipient = $internal ? config('shi3ld.enquiries_email') : $enquiry->email;
                        Mail::to($recipient)->sendNow(new ServiceEnquiryMail($enquiry, $internal));
                        $enquiry->{$column} = now();
                        $enquiry->save();
                    } catch (\Throwable $error) {
                        report($error);
                        $enquiry->touch();
                        $this->warn('Notification pending for enquiry '.$enquiry->reference);
                    }
                }
            }
        } finally {
            $lock->release();
        }

        return self::SUCCESS;
    }
}
