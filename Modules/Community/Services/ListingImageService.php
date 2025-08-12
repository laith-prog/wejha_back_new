<?php

namespace Modules\Community\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Facades\Image;

class ListingImageService
{
    /**
     * Upload listing images
     *
     * @param int $listingId
     * @param array $images
     * @return array
     */
    public function uploadListingImages($listingId, $images)
    {
        $storedImages = [];
        $displayOrder = $this->getNextDisplayOrder($listingId);
        $isPrimary = !$this->hasPrimaryImage($listingId);
        
        foreach ($images as $image) {
            // Generate a unique filename
            $filename = Str::uuid() . '.' . $image->getClientOriginalExtension();
            
            // Store the original image
            $path = $image->storeAs('listings/' . $listingId, $filename, 'public');
            
            // Create a thumbnail
            $thumbnail = Image::make($image);
            $thumbnail->fit(300, 300, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            });
            
            // Save thumbnail
            $thumbnailPath = 'listings/' . $listingId . '/thumb_' . $filename;
            Storage::disk('public')->put($thumbnailPath, (string) $thumbnail->encode());
            
            // Save image record in database
            $imageId = DB::table('listing_images')->insertGetId([
                'listing_id' => $listingId,
                'image_path' => $path,
                'thumbnail_path' => $thumbnailPath,
                'is_primary' => $isPrimary,
                'display_order' => $displayOrder,
                'created_at' => now(),
                'updated_at' => now()
            ]);
            
            $storedImages[] = [
                'id' => $imageId,
                'listing_id' => $listingId,
                'image_path' => $path,
                'thumbnail_path' => $thumbnailPath,
                'is_primary' => $isPrimary,
                'display_order' => $displayOrder
            ];
            
            // Only the first image should be primary
            $isPrimary = false;
            $displayOrder++;
        }
        
        return $storedImages;
    }
    
    /**
     * Delete all images for a listing
     *
     * @param int $listingId
     * @return void
     */
    public function deleteListingImages($listingId)
    {
        // Get all images for the listing
        $images = DB::table('listing_images')->where('listing_id', $listingId)->get();
        
        foreach ($images as $image) {
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
        }
        
        // Delete the image records
        DB::table('listing_images')->where('listing_id', $listingId)->delete();
        
        // Delete the listing directory
        Storage::disk('public')->deleteDirectory('listings/' . $listingId);
    }
    
    /**
     * Set an image as the primary image for a listing
     *
     * @param int $listingId
     * @param int $imageId
     * @return void
     */
    public function setPrimaryImage($listingId, $imageId)
    {
        // Reset all images to non-primary
        DB::table('listing_images')
            ->where('listing_id', $listingId)
            ->update(['is_primary' => false]);
            
        // Set the selected image as primary
        DB::table('listing_images')
            ->where('id', $imageId)
            ->update(['is_primary' => true]);
    }
    
    /**
     * Update the display order of images
     *
     * @param int $listingId
     * @param array $imageOrder
     * @return void
     */
    public function updateImageOrder($listingId, $imageOrder)
    {
        foreach ($imageOrder as $index => $imageId) {
            DB::table('listing_images')
                ->where('id', $imageId)
                ->where('listing_id', $listingId)
                ->update(['display_order' => $index]);
        }
    }
    
    /**
     * Get the next display order for a listing
     *
     * @param int $listingId
     * @return int
     */
    private function getNextDisplayOrder($listingId)
    {
        $maxOrder = DB::table('listing_images')
            ->where('listing_id', $listingId)
            ->max('display_order');
            
        return $maxOrder ? $maxOrder + 1 : 0;
    }
    
    /**
     * Check if the listing has a primary image
     *
     * @param int $listingId
     * @return bool
     */
    private function hasPrimaryImage($listingId)
    {
        return DB::table('listing_images')
            ->where('listing_id', $listingId)
            ->where('is_primary', true)
            ->exists();
    }
} 