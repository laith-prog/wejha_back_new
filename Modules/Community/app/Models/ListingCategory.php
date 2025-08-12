<?php

namespace Modules\Community\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ListingCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'display_name',
        'icon',
        'description',
        'is_active',
        'display_order'
    ];

    public function subcategories()
    {
        return $this->hasMany(ListingSubcategory::class, 'category_id');
    }

    public function listings()
    {
        return $this->hasMany(Listing::class, 'category_id');
    }
}
