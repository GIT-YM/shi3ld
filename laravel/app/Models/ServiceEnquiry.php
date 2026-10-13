<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceEnquiry extends Model
{
    protected $connection = 'mysql';

    protected $fillable = [
        'reference', 'service_type', 'name', 'email', 'phone', 'postcode', 'organisation',
        'site_details', 'frequency', 'preferred_contact', 'preferred_start_date',
        'requirements', 'privacy_acknowledged_at',
        'property_type', 'approximate_size', 'rooms',
    ];

    protected function casts(): array
    {
        return ['privacy_acknowledged_at' => 'datetime', 'preferred_start_date' => 'date', 'customer_notified_at' => 'datetime', 'staff_notified_at' => 'datetime'];
    }
}
