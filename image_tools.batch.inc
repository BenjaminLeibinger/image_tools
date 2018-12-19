<?php




function convertPngsToJpg($images, &$context)
{
    /** @var \Drupal\image_tools\Services\ImageService $imageService */
    $imageService = \Drupal::service('image_tools.conversion.service');

    list($images_converted, $old_size, $new_size) = $imageService->convertPngImagesToJpeg($images);

    $context['results']['images_converted'] = isset($context['results']['images_converted']) ? $context['results']['images_converted'] + $images_converted : $images_converted;
    $context['results']['old_size'] = isset($context['results']['old_size']) ? $context['results']['old_size'] + $old_size : $old_size;
    $context['results']['new_size'] = isset($context['results']['new_size']) ? $context['results']['new_size'] + $new_size : $new_size;
    $context['results']['saved_size'] = isset($context['results']['saved_size']) ? $context['results']['saved_size'] + ($old_size - $new_size) : ($old_size - $new_size);
}


function resizeJPGs($images, $max_width, &$context)
{
    /** @var \Drupal\image_tools\Services\ImageService $imageService */
    $imageService = \Drupal::service('image_tools.conversion.service');

    list($images_converted, $old_size, $new_size) = $imageService->resizeImages($images, $max_width);

    $context['results']['images_converted'] = isset($context['results']['images_converted']) ? $context['results']['images_converted'] + $images_converted : $images_converted;
    $context['results']['old_size'] = isset($context['results']['old_size']) ? $context['results']['old_size'] + $old_size : $old_size;
    $context['results']['new_size'] = isset($context['results']['new_size']) ? $context['results']['new_size'] + $new_size : $new_size;
}


function png_conversion_finished($success, $results, $operations) {
    $messenger = \Drupal::messenger();
    $t = \Drupal::translation();

    if($success)
    {
        $messenger->addMessage($t->translate(
            "Converted " . $results['images_converted'] . " images from png to jpg. We had ".$results['old_size']." MB and reduced it to ".$results['new_size']." MB. We saved ".$results['saved_size']." MB"
        ));
    }else{
        $error_operation = reset($operations);
        $messenger->addMessage($t->translate("Error on Batch operation: ". $error_operation[0]));
    }


}

function jpg_resizing_finished($success, $results, $operations) {
    $messenger = \Drupal::messenger();
    $t = \Drupal::translation();

    if($success)
    {
        $messenger->addMessage($t->translate(
            "Resized " . $results['images_converted'] . " images. We had ".$results['old_size']." MB, now we need  ".$results['new_size']." MB. "
        ));
    }else{
        $error_operation = reset($operations);
        $messenger->addMessage($t->translate("Error on Batch operation: ". $error_operation[0]));
    }


}