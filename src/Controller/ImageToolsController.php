<?php
/**
 * Created by PhpStorm.
 * User: d430974
 * Date: 2018-12-11
 * Time: 16:32
 */

namespace Drupal\image_tools\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\image_tools\Services\ImageService;
use Drupal\image_tools\Form\ResizeJpgsForm;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;


class ImageToolsController extends  ControllerBase
{
    const BATCH_IMAGE_COUNT = 50;


    /** @var ImageService $imageService */
    private $imageService;

    /**
     * DrushImageCommand constructor.
     * @param ImageService $imageService
     */
    public function __construct(ImageService $imageService)
    {
        $this->imageService = $imageService;
    }

    public static function create(ContainerInterface $container) {
        /** @var ImageService $imageService */
        $imageService = $container->get('image_tools.conversion.service');

        return new static($imageService);
    }

    public function overview()
    {
        return [
            '#title' => 'Image Tools Overview',
            '#theme' => 'overview_page',
        ];
    }

    public function showConvertiblePNGs()
    {
        $images = $this->imageService->loadPngImages();

        $rows = [];
        foreach($images as $fid => $element)
        {
            $transparency = $element['transparency'] ? "x" : "";
            $rows[] = [ 'fid' => $fid,  'name' => basename($element['path']), 't' => $transparency];
        }

        $content = [
            '#title' => 'Convert PNGs',
            '#theme' => 'show_convertible_pngs_page',
            '#rows' => $rows
        ];


        return $content;
    }

    public function showResizableJPGs(Request $request)
    {
        $include_png = $request->query->get('include_png') === null ? false : true;
        $max_width = $request->query->get('max_width', $this->imageService::DEFAULT_MAX_WIDTH);

        if($max_width <= 0){
            $max_width = $this->imageService::DEFAULT_MAX_WIDTH;
        }

        $images = $this->imageService->findLargeWidthImages($max_width, $include_png);

        $rows = [];
        foreach($images as $fid => $element)
        {
            $transparency = isset($element['transparency']) && $element['transparency'] ? "x" : "";
            $rows[] = [ 'fid' => $fid,
                        'name' => basename($element['path']),
                        'size' => $element['width'] . 'x' . $element['height'],
                        't' => $transparency];
        }

        $content = [
            '#title' => 'Resize JPGs',
            '#theme' => 'show_resizable_jpgs_page',
            '#max_width' => $max_width,
            '#png' => $include_png ? 'yes' : 'no',
            '#rows' => $rows,
            '#form' => $this->formBuilder()->getForm(ResizeJpgsForm::class, $max_width, $include_png)
        ];

        return $content;
    }

    public function addBatchConvertPNGs()
    {
        $images = $this->imageService->loadPngImages();

        $operations = [];
        if(count($images) > self::BATCH_IMAGE_COUNT){
            $array_chunks = array_chunk($images, self::BATCH_IMAGE_COUNT, true);

            foreach($array_chunks as $images){
                $operations[] = ['convertPngsToJpg', [$images]];
            }
        }else{
            $operations[] = ['convertPngsToJpg', [$images]];
        }

        $batch = array(
            'title' => t('Converting PNGs to JPGs'),
            'operations' => $operations,
            'finished' => 'png_conversion_finished',
            'file' => drupal_get_path('module', 'image_tools') . '/image_tools.batch.inc',
        );

        batch_set($batch);
        return batch_process( Url::fromRoute('image_tools.show_convertible_pngs'));
    }

    public function addBatchResizeJPGs(Request $request)
    {
        $include_png = $request->query->get('png', 'no') === 'yes' ? true : false;
        $max_width = $request->query->get('max_width', $this->imageService::DEFAULT_MAX_WIDTH);

        $images = $this->imageService->findLargeWidthImages($max_width, $include_png);

        $operations = [];
        if(count($images) > self::BATCH_IMAGE_COUNT){
            $array_chunks = array_chunk($images, self::BATCH_IMAGE_COUNT, true);

            foreach($array_chunks as $images){
                $operations[] = ['resizeJPGs', [$images, $max_width]];
            }
        }else{
            $operations[] = ['resizeJPGs', [$images, $max_width]];
        }

        $batch = array(
            'title' => t('Resizing JPGs'),
            'operations' => $operations,
            'finished' => 'jpg_resizing_finished',
            'file' => drupal_get_path('module', 'image_tools') . '/image_tools.batch.inc',
        );

        batch_set($batch);
        return batch_process(Url::fromRoute('image_tools.show_resizeable_jpgs'));
    }

    private function getOperations($images, $function)
    {
        $size = count($images);

        $operations = [];
        if($size > self::BATCH_IMAGE_COUNT){
            $array_chunks = array_chunk($images, self::BATCH_IMAGE_COUNT, true);

            foreach($array_chunks as $images){
                $operations[] = [$function, [$images]];
            }
        }else{
            $operations[] = [$function, [$images]];
        }

        return $operations;
    }
}