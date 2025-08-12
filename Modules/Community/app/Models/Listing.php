<?php

namespace Modules\Community\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Listing extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'price',
        'price_type',
        'currency',
        'post_number',
        'phone_number',
        'category_id',
        'subcategory_id',
        'listing_type', // Keep for backward compatibility
        'purpose',
        'status',
        'facility_under_construction',
        'expected_completion_date',
        'construction_progress_percent',
        'features',
        'location',
        'similar_options',
        'published_at',
        'is_featured',
        'is_promoted',
        'promoted_until',
        'expires_at'
    ];

    protected $casts = [
        'features' => 'array',
        'location' => 'array',
        'similar_options' => 'array',
        'published_at' => 'datetime',
        'promoted_until' => 'datetime',
        'expires_at' => 'datetime',
        'facility_under_construction' => 'boolean',
        'expected_completion_date' => 'date',
        'is_featured' => 'boolean',
        'is_promoted' => 'boolean'
    ];

    protected $table = 'listings';

    public function category()
    {
        return $this->belongsTo(ListingCategory::class, 'category_id');
    }

    public function subcategory()
    {
        return $this->belongsTo(ListingSubcategory::class, 'subcategory_id');
    }

    // Other relationships...
}


