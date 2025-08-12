<?php

namespace Modules\Community\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ListingSubcategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'name',
        'display_name',
        'icon',
        'description',
        'is_active',
        'display_order',
        'attributes'
    ];

    protected $casts = [
        'attributes' => 'array'
    ];

    public function category()
    {
        return $this->belongsTo(ListingCategory::class, 'category_id');
    }

    public function listings()
    {
        return $this->hasMany(Listing::class, 'subcategory_id');
    }
} 