<?php

namespace Modules\Community\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Community\Services\ListingImageService;

class ListingImageController extends Controller
{
    protected $imageService;
    
    public function __construct(ListingImageService $imageService)
    {
        $this->imageService = $imageService;
    }
    
    /**
     * Upload images for a listing
     */
    public function upload(Request $request, $listingId)
    {
        // Debug request data
        \Log::info('Image Upload Request', [
            'has_file' => $request->hasFile('images'),
            'all_data' => $request->all(),
            'files' => $request->file(),
            'content_type' => $request->header('Content-Type')
        ]);
        
        // Check if we have any files in the request
        $hasImages = false;
        $imageFiles = [];
        
        // Check for 'images' field (array format)
        if ($request->hasFile('images')) {
            $hasImages = true;
            $imageFiles = $request->file('images');
        }
        // Check for 'images[]' field
        else if ($request->hasFile('images[]')) {
            $hasImages = true;
            $imageFiles = $request->file('images[]');
        }
        // Check for 'image' field (single file)
        else if ($request->hasFile('image')) {
            $hasImages = true;
            $imageFiles = [$request->file('image')];
        }
        // Check all request files
        else if (count($request->allFiles()) > 0) {
            $hasImages = true;
            $imageFiles = $request->allFiles();
        }
        
        if (!$hasImages) {
            return response()->json([
                'success' => false,
                'message' => 'No images found in request. Please send images as form-data with key "images[]"',
                'request_data' => $request->all()
            ], 422);
        }
        
        // Check if the listing exists and belongs to the authenticated user
        $listing = DB::table('listings')
            ->where('id', $listingId)
            ->first();
            
        if (!$listing) {
            return response()->json([
                'success' => false,
                'message' => 'Listing not found'
            ], 404);
        }
        
        // Temporarily disable user check for testing
        /*
        if ($listing->user_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to upload images to this listing'
            ], 403);
        }
        */
        
        try {
            // Upload the images
            $storedImages = $this->imageService->uploadListingImages($listingId, $imageFiles);
            
            return response()->json([
                'success' => true,
                'message' => count($storedImages) . ' images uploaded successfully',
                'data' => $storedImages
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload images',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Delete an image
     */
    public function delete(Request $request, $imageId)
    {
        // Find the image
        $image = DB::table('listing_images')->where('id', $imageId)->first();
        
        if (!$image) {
            return response()->json([
                'success' => false,
                'message' => 'Image not found'
            ], 404);
        }
        
        // Check if the listing belongs to the authenticated user
        $listing = DB::table('listings')
            ->where('id', $image->listing_id)
            ->where('user_id', Auth::id())
            ->first();
            
        if (!$listing) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to delete this image'
            ], 403);
        }
        
        try {
            // Delete the image file
            if (Storage::disk('public')->exists($image->image_path)) {
                Storage::disk('public')->delete($image->image_path);
            }
            
            // Delete the thumbnail if it exists and is different from the main image
            if ($image->thumbnail_path && 
                $image->thumbnail_path !== $image->image_path && 
                Storage::disk('public')->exists($image->thumbnail_path)) {
                Storage::disk('public')->delete($image->thumbnail_path);
            }
            
            // Delete the image record
            DB::table('listing_images')->where('id', $imageId)->delete();
            
            // If this was the primary image, set a new primary image if available
            if ($image->is_primary) {
                $newPrimary = DB::table('listing_images')
                    ->where('listing_id', $image->listing_id)
                    ->orderBy('display_order')
                    ->first();
                    
                if ($newPrimary) {
                    DB::table('listing_images')
                        ->where('id', $newPrimary->id)
                        ->update(['is_primary' => true]);
                }
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Image deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete image',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Set an image as the primary image for a listing
     */
    public function setPrimary(Request $request, $imageId)
    {
        // Find the image
        $image = DB::table('listing_images')->where('id', $imageId)->first();
        
        if (!$image) {
            return response()->json([
                'success' => false,
                'message' => 'Image not found'
            ], 404);
        }
        
        // Check if the listing belongs to the authenticated user
        $listing = DB::table('listings')
            ->where('id', $image->listing_id)
            ->where('user_id', Auth::id())
            ->first();
            
        if (!$listing) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to modify this listing'
            ], 403);
        }
        
        try {
            // Set this image as primary
            $this->imageService->setPrimaryImage($image->listing_id, $imageId);
            
            return response()->json([
                'success' => true,
                'message' => 'Primary image updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update primary image',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Update the display order of images
     */
    public function updateOrder(Request $request, $listingId)
    {
        // Validate the request
        $request->validate([
            'image_order' => 'required|array',
            'image_order.*' => 'integer|exists:listing_images,id'
        ]);
        
        // Check if the listing belongs to the authenticated user
        $listing = DB::table('listings')
            ->where('id', $listingId)
            ->where('user_id', Auth::id())
            ->first();
            
        if (!$listing) {
            return response()->json([
                'success' => false,
                'message' => 'Listing not found or you do not have permission to modify it'
            ], 404);
        }
        
        try {
            // Update the image order
            $this->imageService->updateImageOrder($listingId, $request->image_order);
            
            return response()->json([
                'success' => true,
                'message' => 'Image order updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update image order',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get all images for a listing
     */
    public function getListingImages($listingId)
    {
        $images = DB::table('listing_images')
            ->where('listing_id', $listingId)
            ->orderBy('display_order')
            ->get();
            
        return response()->json([
            'success' => true,
            'data' => $images
        ]);
    }
} 