<?php

namespace App\Jobs;

use App\Models\ServiceEnquiry;
use App\Services\BlogAttribution;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RecordBlogConversion implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(public int $enquiryId) {}

    public function backoff(): array
    {
        return [60, 300, 900, 3600];
    }

    /**
     * Execute the job.
     */
    public function handle(BlogAttribution $attribution): void
    {
        if ($enquiry = ServiceEnquiry::find($this->enquiryId)) {
            $attribution->record($enquiry);
        }
    }
}
