<?php

use \Drupal\image_tools\Services\ImageService;

const DEFAULT_MAX_WIDTH = 2048;

/**
 * Implements hook_drush_command().
 */
function image_tools_drush_command() {
    $commands = [];

    $commands['convert-png-to-jpg'] = [
        'description' => dt('Convert all Images with type png to jpg'),
        'aliases' => ['cptj'],
        'core' => ['8+'],
        'options' => [
            'dry_run' => 'Display images which should be converted. No image will be modified.'
        ]
    ];

    $commands['resize-images'] = [
        'description' => dt('Resize JPG Images to an width of '.DEFAULT_MAX_WIDTH.' pixel.'),
        'aliases' => ['ri'],
        'core' => ['8+'],
        'options' => [
            'dry_run' => 'Display images which should be converted. No image will be modified.',
            'max_width' => 'The max width for the images. Images larger then max_width getting  resized. Default '.DEFAULT_MAX_WIDTH,
            'include_png' => 'Also includes PNG images. With transparency the background will be white.'
        ]
    ];

    return $commands;
}


function drush_image_tools_convert_png_to_jpg() {
    $dry_run = drush_get_option('dry_run', false);

    /** @var ImageService $imageService */
    $imageService = \Drupal::service('image_tools.conversion.service');

    if($dry_run){

        $files = $imageService->loadPngImages();

        foreach($files as $path => $element)
        {
            drush_print(basename($path) . ($element['transparency'] ? " | has transparency (alpha channel)." : ""));
        }

        return;
    }

    $context = [];
    list($images_converted, $current_size, $new_size, $saved_size)  = $imageService->convertPngImagesToJpeg($context);

    drush_print("Converted $images_converted images from png to jpg. We had $current_size MB and reduced it to $new_size MB. We saved $saved_size MB.");
}

function drush_image_tools_resize_images(){
    $dry_run = drush_get_option('dry_run');
    $max_width = drush_get_option('max_width', DEFAULT_MAX_WIDTH);
    $include_png = drush_get_option('include_png', false);

    /** @var ImageService $imageService */
    $imageService = \Drupal::service('image_tools.conversion.service');

    if($dry_run){
        $files = $imageService->loadLargeImages($max_width, $include_png);

        foreach($files as $path => $element)
        {
            drush_print(basename($path) . (isset($element['transparency']) && $element['transparency'] ? " | has transparency (alpha channel)." : ""));
        }

        return;
    }

    $context = [];
    list($images_converted, $current_size, $new_size) = $imageService->resizeImages($max_width, $include_png, $context);

    drush_print("Resized $images_converted images to the an max width of ". $max_width ." pixels. We had $current_size MB, now we need $new_size MB.");
}