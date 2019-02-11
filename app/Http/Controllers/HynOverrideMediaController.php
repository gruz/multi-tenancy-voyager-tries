<?php

namespace App\Http\Controllers;

use Hyn\Tenancy\Website\Directory;
use Illuminate\Support\Facades\Storage;

/**
 * Class MediaController
 *
 * @use Route::get('/storage/{path}', App\MediaController::class)
 *          ->where('path', '.+')
 *          ->name('tenant.media');
 */
class HynOverrideMediaController extends \Hyn\Tenancy\Controllers\MediaController
{
    /**
     * @var Directory
     */
    private $directory;

    public function __construct(Directory $directory)
    {
        $this->directory = $directory;
    }

    public function __invoke(string $path)
    {
        // $path = "media/$path";

        if ($this->directory->exists($path)) {
            return response($this->directory->get($path))
                ->header('Content-Type', Storage::disk('tenant')->mimeType($path));
        }

        return abort(404);
    }
}
