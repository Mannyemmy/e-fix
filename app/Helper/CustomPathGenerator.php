<?php
namespace App\Helper;

use Spatie\MediaLibrary\Support\PathGenerator\PathGenerator;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class CustomPathGenerator implements PathGenerator{
    /*
     * Get the path for the given media, relative to the root storage path.
     */
    public function getPath(Media $media): string
    {
        return $this->getBasePath($media).'/';
    }

    /*
     * Get the path for conversions of the given media, relative to the root storage path.
     */
    public function getPathForConversions(Media $media): string
    {
        return $this->getBasePath($media).'/conversions/';
    }

    /*
     * Get the path for responsive images of the given media, relative to the root storage path.
     */
    public function getPathForResponsiveImages(Media $media): string
    {
        return $this->getBasePath($media).'/responsive-images/';
    }

    /*
     * Get a unique base path for the given media.
     */
    protected function getBasePath(Media $media): string
    {
        $path = '';
        if(env('MEDIA_DISK') == 'spaces') {
            $spacesPath = env('DO_SPACES_PATH', '');
            // Strip any accidentally included scheme or host (e.g. "https://domain.com/storage/")
            // so that only a relative folder prefix is used — prevents doubled domain in URLs.
            if ($spacesPath) {
                $parsed = parse_url($spacesPath);
                // If it has a host component it is an absolute URL — extract only the path
                if (!empty($parsed['host'])) {
                    $spacesPath = ltrim($parsed['path'] ?? '', '/');
                } else {
                    $spacesPath = ltrim($spacesPath, '/');
                }
                $path = $spacesPath ? rtrim($spacesPath, '/') . '/' : '';
            }
        }
        return $path.$media->getKey();
    }
}