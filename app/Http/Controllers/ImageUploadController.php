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
        // Handle CORS preflight
        if ($request->getMethod() === 'OPTIONS') {
            return response()->json([], 200)
                ->header('Access-Control-Allow-Origin', '*')
                ->header('Access-Control-Allow-Methods', 'POST, OPTIONS')
                ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization');
        }

        $request->validate([
            'image' => 'required|file|max:10240', // Max 10MB, any file type for now
        ]);


        try {
            $image = $request->file('image');
            
            // Upload to Catbox using multipart form data
            $response = Http::asMultipart()->post('https://catbox.moe/user/api.php', [
                [
                    'name' => 'reqtype',
                    'contents' => 'fileupload'
                ],
                [
                    'name' => 'fileToUpload',
                    'contents' => fopen($image->getRealPath(), 'r'),
                    'filename' => $image->getClientOriginalName()
                ]
            ]);

            if ($response->successful()) {
                $imageUrl = trim($response->body());
                
                if (filter_var($imageUrl, FILTER_VALIDATE_URL)) {
                    return response()->json([
                        'success' => true,
                        'url' => $imageUrl
                    ])->header('Access-Control-Allow-Origin', '*');
                }
            }

            return response()->json([
                'success' => false,
                'error' => 'Upload failed: ' . $response->body()
            ], 500)->header('Access-Control-Allow-Origin', '*');

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500)->header('Access-Control-Allow-Origin', '*');
        }
    }
}
