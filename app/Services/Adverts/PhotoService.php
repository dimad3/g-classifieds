<?php

declare(strict_types=1);

namespace App\Services\Adverts;

use App\Http\Requests\Adverts\StoreRequest;
use App\Models\Adverts\Advert\Advert;
use App\Models\Adverts\Advert\Photo;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Facades\Image;

class PhotoService
{
    /**
     * Update advert's photos in storage.
     */
    public function updatePhotos(Advert $advert, StoreRequest $request): void
    {
        // If before this request validation has been failed then pending photos are presented and rendered
        // so we need this block of code
        if ($advert->hasPendingPhotos()) {
            $this->deleteActivePhotos($advert);

            // If new photos are chosen (user want to change previously chosen photos)
            if ($request->hasFile('files')) {
                // remove pending photos and add chosen photos instead of them
                $this->deletePendingPhotos($advert);
                $this->addActivePhotos($advert, $request);
            }
            // if new photos are NOT chosen (user does NOT want to change previously chosen photos)
            else {
                // remove active photos and just change pending photos status to 'active'
                $advert->pendingPhotos()->update(['status' => 'active']);
            }
        } else { // If before this request validation has NOT been failed then pending photos are NOT presented and rendered
            // so we need this block of code
            // If photos are chosen (user want to change current photos)
            if ($request->hasFile('files')) {
                // current active photos are NOT needed any more, so remove active photos and add chosen photos instead of them
                $this->deleteActivePhotos($advert);
                $this->addActivePhotos($advert, $request);
            }
        }
    }

    /**
     * Add advert's ACTIVE photos.
     */
    public function addActivePhotos(Advert $advert, StoreRequest $request): void
    {
        DB::transaction(function () use ($advert, $request): void {
            if ($request->hasFile('files')) {
                foreach ($request['files'] as $file) {
                    $path = $this->addPhoto($advert, $file);
                    $advert->photos()->create([
                        'file' => $path,
                        'status' => 'active',
                    ]);
                }
            }
        });
    }

    /**
     * Add advert's ACTIVE photos.
     */
    public function addPendingPhotos(Advert $advert, StoreRequest $request): void
    {
        DB::transaction(function () use ($advert, $request): void {
            if ($request->hasFile('files')) {
                foreach ($request['files'] as $file) {
                    $path = $this->addPhoto($advert, $file);
                    $advert->photos()->create([
                        'file' => $path,
                        'status' => 'pending',
                    ]);
                }
            }
        });
    }

    /**
     * Remove ALL advert's active photos from storage && from db.
     */
    public function deleteActivePhotos(Advert $advert): void
    {
        if ($advert->hasActivePhotos()) {
            foreach ($advert->activePhotos as $key => $photo) {
                $this->deletePhoto($photo);
            }
        }
        $advert->activePhotos()->delete();
    }

    /**
     * Remove ALL advert's pending photos from storage && from db.
     */
    public function deletePendingPhotos(Advert $advert): void
    {
        if ($advert->hasPendingPhotos()) {
            foreach ($advert->pendingPhotos as $key => $photo) {
                $this->deletePhoto($photo);
            }
            $advert->pendingPhotos()->delete();
        }
    }

    /**
     * Add a single photo to the storage.
     *
     * This method processes the uploaded photo, fixes its orientation
     * based on EXIF data, resizes it while maintaining its aspect ratio,
     * and then saves it to the specified path in the storage.
     *
     * @param  Advert  $advert  The advert object associated with the photo.
     * @param  UploadedFile  $photo  The uploaded photo file to be stored.
     * @return string|null The path of the stored photo if successful, null otherwise.
     */
    private function addPhoto(Advert $advert, UploadedFile $photo): ?string
    {
        // Generate a timestamp for the filename
        $timeStamp = Carbon::now()->format('Ymd_His');

        // Create a unique name for the photo
        $name = $timeStamp . '_' . Str::random(5);

        // Get the file extension of the uploaded photo
        $extension = $photo->getClientOriginalExtension();

        // Define the path where the photo will be stored, converting to lowercase
        $path = strtolower("adverts/{$advert->id}/{$name}.{$extension}");

        // Process the image with Intervention
        $photo = Image::make($photo)
            ->orientate() // Fix the orientation based on EXIF data
            // ->fit(680, 400, function ($constraint) {
            //     $constraint->aspectRatio();
            // })
            // Resize the image while maintaining the aspect ratio
            ->resize(910, null, function ($constraint): void {
                $constraint->aspectRatio();
            })
            ->encode($extension); // Encode the image to the original extension

        // Store the processed image in the specified path on the public disk
        $added = Storage::disk('public')->put($path, $photo);

        // If the image was successfully added, return the path; otherwise return null
        return $added ? $path : null;
    }

    /**
     * Remove ONE photo from storage.
     */
    private function deletePhoto(Photo $photo): void
    {
        if (Storage::disk('public')->exists($photo->file)) {
            Storage::disk('public')->delete($photo->file);
        } else {
            // $url = Storage::url($photo->file);
            // throw new \DomainException("File: {$url} does not exist."); // put the error in the session
        }
    }
}
