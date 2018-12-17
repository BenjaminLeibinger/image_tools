<?php




function convertPngsToJpg(&$context)
{
    /** @var \Drupal\image_tools\Services\ImageService $imageService */
    $imageService = \Drupal::service('image_tools.conversion.service');

    list($images_converted, $current_size, $new_size) = $imageService->convertPngImagesToJpeg();

    $context['results']['images_converted'] = $images_converted;
    $context['results']['old_size'] = $current_size;
    $context['results']['new_size'] = $new_size;
    $context['results']['saved_size'] = $current_size - $new_size;
}


function resizeJPGs($max_width, $include_pngs, &$context)
{
    /** @var \Drupal\image_tools\Services\ImageService $imageService */
    $imageService = \Drupal::service('image_tools.conversion.service');

    list($images_converted, $current_size, $new_size) = $imageService->resizeImages($max_width, $include_pngs);

    $context['results']['images_converted'] = $images_converted;
    $context['results']['old_size'] = $current_size;
    $context['results']['new_size'] = $new_size;
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