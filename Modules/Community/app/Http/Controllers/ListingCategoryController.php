<?php

namespace Modules\Community\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;

class ListingCategoryController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|unique:listing_categories,name',
            'display_name' => 'required|string',
        ]);

        $category = DB::table('listing_categories')->insertGetId([
            'name' => $request->name,
            'display_name' => $request->display_name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['success' => true, 'id' => $category], 201);
    }

    public function storeSubcategory(Request $request)
    {
        $request->validate([
            'category_id' => 'required|exists:listing_categories,id',
            'name' => 'required|string|unique:listing_subcategories,name',
            'display_name' => 'required|string',
        ]);

        $subcategory = DB::table('listing_subcategories')->insertGetId([
            'category_id' => $request->category_id,
            'name' => $request->name,
            'display_name' => $request->display_name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['success' => true, 'id' => $subcategory], 201);
    }
}
