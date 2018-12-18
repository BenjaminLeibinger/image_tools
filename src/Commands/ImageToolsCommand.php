<?php

namespace Drupal\image_tools\Commands;

use Drush\Commands\DrushCommands;
use \Drupal\image_tools\Services\ImageService;

const DEFAULT_MAX_WIDTH = 2048;


class ImageToolsCommand extends DrushCommands {


    /** @var ImageService $imageService */
    private $imageService;

    /**
     * DrushImageCommand constructor.
     * @param ImageService $imageService
     */
    public function __construct(ImageService $imageService)
    {
        parent::__construct();
        $this->imageService = $imageService;
    }

    /**
     * Convert all Images with type png to jpg.
     *
     * @param array $options
     *   An associative array of options whose values come from cli, aliases, config, etc.
     * @option dry_run
     *   Display images which should be converted. No image will be modified.
     *
     *
     * @command image:convertPngToJpeg
     * @aliases i:cptj
     * @throws
     */
    public function convertPngToJpeg($options = ['dry_run' => false])
    {
        if($options['dry_run']){
            $files = $this->imageService->loadPngImages();

            foreach($files as $fid => $element)
            {
                drush_print($fid . " | " . basename($element['path']) . ($element['transparency'] ? " | has transparency (alpha channel)." : ""));
            }

            return;
        }

        list($images_converted, $current_size, $new_size, $saved_size) = $this->imageService->convertPngImagesToJpeg();

        $this->logger()->success( "Converted $images_converted images from png to jpg. We had $current_size MB and reduced it to $new_size MB. We saved $saved_size MB." );
    }


    /**
     * Resize images to a given max width. Default 2048.
     *
     * @param array $options
     *   An associative array of options whose values come from cli, aliases, config, etc.
     * @option dry_run
     *   Display images which should be converted. No image will be modified.
     * @option max_width
     *   The max width for the images. Images larger then max_width getting  resized. Default 2048
     * @option include_png
     *   Also includes PNG images. With transparency the background will be white.
     *
     * @command image:resize
     * @aliases i:r
     * @throws
     */
    public function resizeImages($options = ['dry_run' => false, 'max_width' => DEFAULT_MAX_WIDTH, 'include_png' => false])
    {

        if($options['dry_run']){
            $files = $this->imageService->findLargeWidthImages($options['max_width'], $options['include_png']);

            foreach($files as $fid => $element)
            {
                drush_print($fid . " | " . basename($element['path']) . (isset($element['transparency']) && $element['transparency'] ? " | has transparency (alpha channel)." : ""));
            }

            return;
        }

        list($images_converted, $current_size, $new_size) = $this->imageService->resizeImages($options['max_width'], $options['include_png']);

        $this->logger()->success(dt("Resized $images_converted images to the an max width of ". $options['max_width'] ." pixels. We had $current_size MB, now we need $new_size MB."));
    }

    /**
     * Create Test Images
     *
     * @param array $options
     *   An associative array of options whose values come from cli, aliases, config, etc.
     * @option amount
     *   The amount of images to create.
     *
     * @command image:create:demo
     * @aliases i:cd
     * @throws
     */
    public function createDemoImages($options = ['amount' => 1000, 'width' => 2100])
    {
        $directory = 'public://'.date("Y-m").'/';
        file_prepare_directory($directory, FILE_CREATE_DIRECTORY);

        for($i = 0; $i < $options['amount']; $i++){
            $image = $this->createImage($options['width'], round(rand($options['width']-100, $options['width']+100) * 0.75 ));

            $file = file_save_data(file_get_contents($image), $directory . basename($image), FILE_EXISTS_REPLACE);
            $file->setOwnerId(1);
            $file->save();

            unlink($image);
        }

        $this->logger()->success("created " . $options['amount'] . " images.");
    }


    private function createImage($width, $height){
        $file = $this->generateRandomString();
        $filename = sys_get_temp_dir() . "/" . $file . ".png";
        $im = imagecreate($width, $height);
        $background_color = imagecolorallocate($im, 255, 255, 255);
        $text_color = imagecolorallocate($im, rand(1, 254), rand(1, 254), rand(1, 254));
        imagestring($im, rand(3, 5), rand(10, 200), rand(10, 200), $file, $text_color);
        imagepng($im, $filename);
        imagedestroy($im);

        return $filename;
    }

    private function generateRandomString($length = 10) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }

}
