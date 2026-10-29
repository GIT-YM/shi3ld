<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactMessage extends Model
{
    protected $connection = 'mysql';

    protected $fillable = [
        'reference', 'name', 'email', 'phone', 'subject', 'message', 'privacy_acknowledged_at', 'status', 'staff_notes',
    ];

    protected function casts(): array
    {
        return ['privacy_acknowledged_at' => 'datetime'];
    }
}
