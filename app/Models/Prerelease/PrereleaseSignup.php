<?php

namespace Domain\App\Models\Prerelease;

use EloquentFilter\Filterable;
use Illuminate\Database\Eloquent\Model;
use Rennokki\QueryCache\Traits\QueryCacheable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class PrereleaseSignup extends Model
{
    use HasFactory, Filterable, QueryCacheable, HasUuids;

    protected $table = 'prerelease_signups';

    protected $guarded = ['id'];

    public $sortable = [
        'id',
        'name',
        'email',
        'contact_phone',
        'business_website',
        'business_instagram',
        'preferred_language',
        'created_at',
    ];
}
