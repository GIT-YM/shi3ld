<?php

namespace App\Models;

use Database\Factories\ServiceEnquiryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServiceEnquiry extends Model
{
    /** @use HasFactory<ServiceEnquiryFactory> */
    use HasFactory;

    protected $connection = 'mysql';

    protected $fillable = [
        'reference', 'service_type', 'name', 'email', 'phone', 'postcode', 'organisation',
        'site_details', 'frequency', 'preferred_contact', 'preferred_start_date',
        'requirements', 'privacy_acknowledged_at',
        'property_type', 'approximate_size', 'rooms',
        'blog_post_id', 'blog_attribution_token', 'blog_attributed_at',
    ];

    protected $hidden = ['blog_attribution_token'];

    protected function casts(): array
    {
        return ['blog_post_id' => 'integer', 'blog_attribution_token' => 'encrypted', 'blog_attributed_at' => 'datetime', 'blog_conversion_recorded_at' => 'datetime', 'privacy_acknowledged_at' => 'datetime', 'preferred_start_date' => 'date', 'customer_notified_at' => 'datetime', 'staff_notified_at' => 'datetime', 'customer_notification_attempts' => 'integer', 'staff_notification_attempts' => 'integer', 'customer_notification_next_attempt_at' => 'datetime', 'staff_notification_next_attempt_at' => 'datetime', 'customer_notification_failed_at' => 'datetime', 'staff_notification_failed_at' => 'datetime'];
    }
}
