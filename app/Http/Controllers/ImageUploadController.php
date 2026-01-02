<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ImageUploadController extends Controller
{
    /**
     * Upload image to Catbox.moe
     */
    public function uploadToCatbox(Request $request)
    {
        $request->validate([
            'image' => 'required|image|max:10240', // Max 10MB
        ]);

        try {
            $image = $request->file('image');
            
            // Upload to Catbox using multipart
            $response = Http::attach(
                'fileToUpload',
                file_get_contents($image->getRealPath()),
                $image->getClientOriginalName()
            )->post('https://catbox.moe/user/api.php', [
                'reqtype' => 'fileupload'
            ]);

            if ($response->successful()) {
                $imageUrl = trim($response->body());
                
                if (filter_var($imageUrl, FILTER_VALIDATE_URL)) {
                    return response()->json([
                        'success' => true,
                        'url' => $imageUrl
                    ]);
                }
            }

            return response()->json([
                'success' => false,
                'error' => 'Upload failed: ' . $response->body()
            ], 500);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
