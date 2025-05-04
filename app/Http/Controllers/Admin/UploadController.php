<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class UploadController extends Controller
{
    /**
     * Upload image and Get the public URL of the uploaded file
     */
    public function image(Request $request): string
    {
        $this->validate($request, [
            'file' => 'required|image|mimes:jpg,jpeg,png',
        ]);

        $file = $request->file('file');
        $path = $file->store('images', 'public');

        // Get an instance of the public disk -> Get the public URL of the uploaded file
        return Storage::disk('public')->url($path);
    }
}
