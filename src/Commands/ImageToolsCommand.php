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

            foreach($files as $path => $element)
            {
                drush_print(basename($path) . ($element['transparency'] ? " | has transparency (alpha channel)." : ""));
            }

            return;
        }

        $images_converted = $this->imageService->convertPngToJpeg();

        $this->logger()->success(dt("Converted $images_converted images from png to jpg."));
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
            $files = $this->imageService->loadLargeImages($options['max_width'], $options['include_png']);

            foreach($files as $path => $element)
            {
                drush_print(basename($path) . (isset($element['transparency']) && $element['transparency'] ? " | has transparency (alpha channel)." : ""));
            }

            return;
        }

        $images_converted = $this->imageService->resizeImages($options['max_width'], $options['include_png']);

        $this->logger()->success(dt("Resized $images_converted images to the an max width of ". $options['max_width'] ." pixels."));
    }


}
