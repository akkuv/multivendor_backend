<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class UploadController extends Controller
{
    public function storeImage(Request $request): JsonResponse
    {
        $data = $request->validate([
            'image' => ['required', 'image', 'mimes:jpeg,png,jpg,gif,webp,svg', 'max:5120'],
        ]);

        /** @var UploadedFile $file */
        $file = $data['image'];

        $directory = 'uploads/images/'.now()->format('Y-m');
        $filename = Str::uuid().'.'.$file->getClientOriginalExtension();

        $file->move(public_path($directory), $filename);

        $path = '/'.$directory.'/'.$filename;

        // Return root-relative path so frontend can resolve against current API origin.
        return $this->success(__('Image uploaded successfully.'), [
            'url' => $path,
        ], 201);
    }

    public function storeVideo(Request $request): JsonResponse
    {
        // A file above the PHP ini cap (upload_max_filesize) never arrives intact:
        // PHP truncates it and flags UPLOAD_ERR_INI_SIZE, so the size never
        // reaches the `max` rule and would surface as a vague "failed to upload".
        $video = $request->file('video');
        if ($video instanceof UploadedFile && in_array($video->getError(), [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
            return $this->validationError(
                ['video' => [__('The video may not be greater than 50 MB.')]],
            );
        }

        $data = $request->validate([
            'video' => [
                'required',
                'file',
                'mimetypes:video/mp4,video/quicktime,video/webm,video/x-msvideo,video/x-matroska,video/3gpp',
                // Kept in step with the server's upload_max_filesize/post_max_size
                // (50M/52M) so oversized files fail with a readable validation
                // error instead of a bare 413 from the PHP layer.
                'max:51200',
            ],
        ]);

        /** @var UploadedFile $file */
        $file = $data['video'];

        $directory = 'uploads/videos/'.now()->format('Y-m');
        $filename = Str::uuid().'.'.$file->getClientOriginalExtension();

        $file->move(public_path($directory), $filename);

        $path = '/'.$directory.'/'.$filename;

        // Return root-relative path so frontend can resolve against current API origin.
        return $this->success(__('Video uploaded successfully.'), [
            'url' => $path,
        ], 201);
    }
}
