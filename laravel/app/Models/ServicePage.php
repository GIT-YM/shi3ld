<?php

namespace App\Models;

use Database\Factories\ServicePageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServicePage extends Model
{
    /** @use HasFactory<ServicePageFactory> */
    use HasFactory;

    protected $connection = 'mysql';

    protected $fillable = [
        'slug', 'category', 'title', 'eyebrow', 'summary', 'description',
        'tasks', 'extras', 'preparation', 'frequency', 'faqs', 'published',
    ];

    protected function casts(): array
    {
        return ['tasks' => 'array', 'extras' => 'array', 'faqs' => 'array', 'published' => 'boolean'];
    }
}
