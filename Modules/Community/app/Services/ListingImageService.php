<?php

namespace Modules\Community\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;

class ListingImageService
{
    /**
     * Upload and store images for a listing
     *
     * @param int $listingId The ID of the listing
     * @param array|UploadedFile $images Array of image files or single image
     * @param string $disk Storage disk to use (default: 'public')
     * @return array Array of stored image records
     */
    public function uploadListingImages($listingId, $images, $disk = 'public')
    {
        $storedImages = [];
        
        // Handle both single image and array of images
        $images = is_array($images) ? $images : [$images];
        
        foreach ($images as $index => $image) {
            if (!$image instanceof UploadedFile || !$image->isValid()) {
                continue;
            }
            
            // Generate unique path for the image
            $path = $image->store('listings/' . $listingId, $disk);
            
            // Create thumbnail
            $thumbnailPath = $this->createThumbnail($image, $listingId, $disk);
            
            // Store image record in database
            $imageRecord = [
                'listing_id' => $listingId,
                'image_path' => $path,
                'thumbnail_path' => $thumbnailPath ?? $path,
                'is_primary' => $index === 0, // First image is primary
                'display_order' => $index,
                'created_at' => now(),
                'updated_at' => now()
            ];
            
            $imageId = DB::table('listing_images')->insertGetId($imageRecord);
            $imageRecord['id'] = $imageId;
            
            $storedImages[] = $imageRecord;
        }
        
        return $storedImages;
    }
    
    /**
     * Create a thumbnail version of the uploaded image
     *
     * @param UploadedFile $image
     * @param int $listingId
     * @param string $disk
     * @return string|null Path to the thumbnail
     */
    protected function createThumbnail(UploadedFile $image, $listingId, $disk = 'public')
    {
        try {
            // Check if Intervention Image is available
            if (!class_exists('Intervention\Image\Facades\Image')) {
                return null;
            }
            
            $thumbnailName = 'thumb_' . time() . '_' . $image->getClientOriginalName();
            $thumbnailPath = 'listings/' . $listingId . '/thumbnails';
            
            // Create the directory if it doesn't exist
            if (!Storage::disk($disk)->exists($thumbnailPath)) {
                Storage::disk($disk)->makeDirectory($thumbnailPath);
            }
            
            // Create thumbnail using Intervention Image
            $img = Image::make($image->getRealPath());
            $img->fit(300, 300, function ($constraint) {
                $constraint->upsize();
            });
            
            // Save thumbnail
            $fullPath = $thumbnailPath . '/' . $thumbnailName;
            Storage::disk($disk)->put($fullPath, (string) $img->encode());
            
            return $fullPath;
        } catch (\Exception $e) {
            // If thumbnail creation fails, return null (will use original image)
            return null;
        }
    }
    
    /**
     * Delete all images associated with a listing
     *
     * @param int $listingId
     * @param string $disk
     * @return bool
     */
    public function deleteListingImages($listingId, $disk = 'public')
    {
        $images = DB::table('listing_images')
            ->where('listing_id', $listingId)
            ->get();
            
        foreach ($images as $image) {
            // Delete the image file
            if (Storage::disk($disk)->exists($image->image_path)) {
                Storage::disk($disk)->delete($image->image_path);
            }
            
            // Delete the thumbnail if it exists and is different from the main image
            if ($image->thumbnail_path && 
                $image->thumbnail_path !== $image->image_path && 
                Storage::disk($disk)->exists($image->thumbnail_path)) {
                Storage::disk($disk)->delete($image->thumbnail_path);
            }
        }
        
        // Delete image records from database
        return DB::table('listing_images')
            ->where('listing_id', $listingId)
            ->delete();
    }
    
    /**
     * Update the primary image for a listing
     *
     * @param int $listingId
     * @param int $imageId
     * @return bool
     */
    public function setPrimaryImage($listingId, $imageId)
    {
        // First, set all images as non-primary
        DB::table('listing_images')
            ->where('listing_id', $listingId)
            ->update(['is_primary' => false]);
            
        // Then set the selected image as primary
        return DB::table('listing_images')
            ->where('id', $imageId)
            ->where('listing_id', $listingId)
            ->update(['is_primary' => true]);
    }
    
    /**
     * Update the display order of images
     *
     * @param int $listingId
     * @param array $imageOrder Array of image IDs in the desired order
     * @return bool
     */
    public function updateImageOrder($listingId, array $imageOrder)
    {
        $success = true;
        
        foreach ($imageOrder as $index => $imageId) {
            $updated = DB::table('listing_images')
                ->where('id', $imageId)
                ->where('listing_id', $listingId)
                ->update(['display_order' => $index]);
                
            if (!$updated) {
                $success = false;
            }
        }
        
        return $success;
    }
} 