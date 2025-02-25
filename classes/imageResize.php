<?php

namespace App;

use Exception;

class imageResize
{
    private $config;
    private $configParam;
    private $mode;
    private $archivePath;
    private $cachePath;

    /**
     * 
     * @param \App\handleConfig $config
     */
    public function __construct(handleConfig $config)
    {
        $this->config = $config;

        $this->configParam = $this->config->getConfig();
        $this->mode = $this->config->get('mode');
        $this->archivePath = rtrim($this->config->get('archive'), '/') . '/';
        $this->cachePath = rtrim($this->config->get('imageCache'), '/') . '/';
    }

    /**
     * Resizes an image based on the provided size configuration.
     *
     * @param string $filename The name of the image file.
     * @param string $sizeName The size configuration name.
     * @return string The path to the resized image.
     * @throws Exception If the file or size configuration is not found.
     */
    public function resize($filename, $size): string
    {
        $filePath = $this->archivePath . $filename;

        if (!file_exists($filePath)) {
            throw new Exception("File not found: $filePath");
        }

        $sizeConfigW = $this->config->get($size."/width");
        if (!$sizeConfigW) {
            throw new Exception("Width size configuration not found: $size");
        }

        $sizeConfigH = $this->config->get($size."/height");
        if (!$sizeConfigH) {
            throw new Exception("Height size configuration not found: $size");
        }

        $width = $this->config->get($size."/width");
        $height = $this->config->get($size."/height");
        $crop = $this->config->get($size."/crop") ?? false;
        $filters = $this->config->get($size."/filters") ?? [];

        $sizeName = $sizeConfigW."_".$sizeConfigH."_".$crop;

        $cacheFile = $this->getCacheFilename($filename, $sizeName);
        if ($this->isCacheValid($filePath, $cacheFile)) {
            return $cacheFile;
        }

        list($origWidth, $origHeight, $imageType) = getimagesize($filePath);
        list($newWidth, $newHeight) = $this->calculateNewSize($origWidth, $origHeight, $width, $height, $crop);

        if ($this->mode === 'GDLIB') {
            $image = $this->resizeGD($filePath, $newWidth, $newHeight, $crop, $imageType);
        } elseif ($this->mode === 'ImageMagick') {
            $image = $this->resizeImageMagick($filePath, $newWidth, $newHeight, $crop);
        } else {
            throw new Exception("Unsupported image processing mode: $this->mode");
        }

        $this->applyFilters($image, $filters);
        $this->saveImage($image, $cacheFile, $imageType);

        return $cacheFile;
    }

    /**
     * 
     * @param mixed $filename
     * @param mixed $sizeName
     * @return string
     */
    private function getCacheFilename($filename, $sizeName) {
        return $this->cachePath . pathinfo($filename, PATHINFO_FILENAME) . "_" . $sizeName . ".jpg";
    }

    /**
     * Checks if the cache file is valid by comparing the modification times of the original file and the cache file.
     *
     * @param string $originalFile The path to the original file.
     * @param string $cacheFile The path to the cache file.
     * @return bool Returns true if the cache file exists and its modification time is greater than or equal to the original file's modification time, false otherwise.
     */
    private function isCacheValid($originalFile, $cacheFile) {
        return file_exists($cacheFile) && filemtime($originalFile) <= filemtime($cacheFile);
    }

    /**
     * Calculate the new dimensions for an image based on the original dimensions,
     * desired dimensions, and whether cropping is required.
     *
     * @param int $origWidth The original width of the image.
     * @param int $origHeight The original height of the image.
     * @param int|string $width The desired width of the image. Use '*' to auto-calculate based on height.
     * @param int|string $height The desired height of the image. Use '*' to auto-calculate based on width.
     * @param bool $crop Whether to crop the image to fit the desired dimensions.
     * @return array An array containing the new width and height.
     */
    private function calculateNewSize($origWidth, $origHeight, $width, $height, $crop) {
        if ($width === '*') {
            $width = ($height / $origHeight) * $origWidth;
        } elseif ($height === '*') {
            $height = ($width / $origWidth) * $origHeight;
        }

        if (!$crop) {
            $ratio = min($width / $origWidth, $height / $origHeight);
            $width = round($origWidth * $ratio);
            $height = round($origHeight * $ratio);
        }

        return [$width, $height];
    }

    /**
     * Resizes an image using the GD library.
     *
     * @param string $filePath The path to the image file.
     * @param int $newWidth The desired width of the resized image.
     * @param int $newHeight The desired height of the resized image.
     * @param bool $crop Whether to crop the image (currently unused).
     * @param int $imageType The type of the image (IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF).
     * 
     * @return resource The resized image resource.
     * 
     * @throws Exception If the image format is unsupported.
     */
    private function resizeGD($filePath, $newWidth, $newHeight, $crop, $imageType) {
        switch ($imageType) {
            case IMAGETYPE_JPEG:
                $src = imagecreatefromjpeg($filePath);
                break;
            case IMAGETYPE_PNG:
                $src = imagecreatefrompng($filePath);
                break;
            case IMAGETYPE_GIF:
                $src = imagecreatefromgif($filePath);
                break;
            default:
                throw new Exception("Unsupported image format");
        }

        $dst = imagecreatetruecolor($newWidth, $newHeight);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newWidth, $newHeight, imagesx($src), imagesy($src));
        return $dst;
    }

    /**
     * Resizes an image using ImageMagick.
     *
     * @param string $filePath The path to the image file.
     * @param int $newWidth The new width for the image.
     * @param int $newHeight The new height for the image.
     * @param bool $crop Whether to crop the image to the new dimensions or just resize it.
     * @return \Imagick The resized image object.
     */
    private function resizeImageMagick($filePath, $newWidth, $newHeight, $crop) {
        $imagick = new \Imagick($filePath);
        if ($crop) {
            $imagick->cropThumbnailImage($newWidth, $newHeight);
        } else {
            $imagick->resizeImage($newWidth, $newHeight, \Imagick::FILTER_LANCZOS, 1);
        }
        return $imagick;
    }

    private function applyFilters($image, $filters) {
        // Placeholder for future filters implementation
    }

    /**
     * Saves the given image to the specified cache file in the appropriate format.
     *
     * @param $image The image resource to be saved.
     * @param string $cacheFile The path to the file where the image will be saved.
     * @param int $imageType The type of the image (IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF).
     *
     * @return void
     */
    private function saveImage($image, $cacheFile, $imageType) {
        if ($this->mode === 'GDLIB') {
            switch ($imageType) {
                case IMAGETYPE_JPEG:
                    imagejpeg($image, $cacheFile, 90);
                    break;
                case IMAGETYPE_PNG:
                    imagepng($image, $cacheFile);
                    break;
                case IMAGETYPE_GIF:
                    imagegif($image, $cacheFile);
                    break;
                default:
                    throw new Exception("Unsupported image format");
            }
        } elseif ($this->mode === 'ImageMagick') {
            switch ($imageType) {
                case IMAGETYPE_JPEG:
                    $image->setImageFormat('jpeg');
                    $image->setImageCompressionQuality(90);
                    break;
                case IMAGETYPE_PNG:
                    $image->setImageFormat('png');
                    break;
                case IMAGETYPE_GIF:
                    $image->setImageFormat('gif');
                    break;
                default:
                    throw new Exception("Unsupported image format");
            }
            $image->writeImage($cacheFile);
        } else {
            throw new Exception("Unsupported image processing mode: $this->mode");
        }
    }
}