<?php

namespace Drupal\image_tools\Services;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystem;
use Drupal\Core\Database\Connection;
use Drupal\file\Entity\File;
use Drupal\file\FileInterface;
use Drupal\media_entity\Entity\Media;

class ImageService {

    const DEFAULT_MAX_WIDTH = 2048;

    /** @var FileSystem */
    private $filesystem;

    /** @var EntityTypeManagerInterface */
    private $entityManager;

    /** @var Connection */
    private $db;

    /**
     * DrushImageCommandsCommands constructor.
     * @param FileSystem $filesystem
     * @param EntityTypeManagerInterface $entityManager
     * @param Connection $db
     */
    public function __construct(FileSystem $filesystem, EntityTypeManagerInterface $entityManager, Connection $db)
    {
        $this->filesystem = $filesystem;
        $this->entityManager = $entityManager;
        $this->db = $db;
    }


    /**
     * @return array
     * @throws
     */
    public function loadPngImages()
    {
        $file_storage = $this->entityManager->getStorage('file');

        $result = $file_storage->loadByProperties(['filemime' => 'image/png']);

        $files = [];
        foreach($result as $file) {
            /** @var File $file */
            if (strpos($file->getFileUri(), 'media-icons') !== false) {
                unset($file);
                continue;
            }

            $image_path = $this->filesystem->realpath($file->getFileUri());
            $t = $this->detect_transparency($image_path);

            $fid = $this->getFid($file);
            $files[$fid] = ['file' => $file, 'path' => $image_path, 'transparency' => $t];
        }

        return $files;
    }

    /**
     * @param $files
     * @return array
     */
    public function convertPngImagesToJpeg($files)
    {
        $current_size = 0;
        $new_size = 0;
        $images_converted = 0;
        foreach($files as $fid => $element)
        {
            if(!file_exists($element['path'])){continue;}
            if($element['transparency']){continue;}

            $current_size += filesize($element['path']);
            $new_path = $this->convertPngToJpeg($element['path'], $element['file']);
            $new_size += filesize($new_path);

            $images_converted++;
        }

        $current_size = round($current_size / 1024 / 1024, 2);
        $new_size = round($new_size / 1024 / 1024, 2);

        return [$images_converted, $current_size, $new_size, $current_size - $new_size];
    }


    /**
     * Convert all Images with type png to jpg.
     *
     * @param $path
     * @param File $file
     *
     * @throws
     * @return int
     */
    public function convertPngToJpeg($path, File $file)
    {
        $image_path = dirname($path);
        $image_name_jpg = preg_replace('"\.png$"', '.jpg', $file->getFilename());
        $image_path_jpg = $image_path . DIRECTORY_SEPARATOR . $image_name_jpg;

        $this->gd_png2jpg($path, $image_path_jpg);

        $file->setFileUri(preg_replace('"\.png$"', '.jpg', $file->getFileUri()));
        $file->setFilename($image_name_jpg);
        $file->setMimeType('image/jpeg');

        $file->save();
        unlink($path);

        return $image_path_jpg;
    }


    /**
     * @param $max_width
     * @param $include_png
     * @return array
     * @throws
     */
    public function findLargeWidthImages($max_width, $include_png)
    {
        $file_storage = $this->entityManager->getStorage('file');
        $result = $file_storage->loadByProperties(['filemime' => 'image/jpeg']);

        if($include_png){
            $pngs = $file_storage->loadByProperties(['filemime' => 'image/png']);
            $result = array_merge($result, $pngs);
        }

        $files = [];
        foreach($result as $file) {
            /** @var File $file */
            $image_path = $this->filesystem->realpath($file->getFileUri());

            if(!file_exists($image_path)){continue;}

            list($width, $height, $type) = getimagesize($image_path);

            if($width > $max_width){
                $fid = $this->getFid($file);
                $files[$fid] = ['file' => $file, 'path' => $image_path, 'width' => $width, 'height' => $height];

                if($type === IMAGETYPE_PNG){
                    $files[$fid]['transparency'] = $this->detect_transparency($image_path);
                }
            }
        }

        /**
         * Table exists in burdamagazinorg/thunder-project and needs also be updated.
         */
        if(!empty($files) && $this->db->schema()->tableExists('media__field_image'))
        {
            $connection = \Drupal::database();
            $mids = $connection->query("SELECT entity_id FROM media__field_image where field_image_target_id IN (:fids[])", [':fids[]' => array_keys($files)])->fetchAllAssoc('entity_id');

            $media_type_storage = $this->entityManager->getStorage('media');
            $media_images = $media_type_storage->loadMultiple(array_keys($mids));

            foreach($media_images as $media) {
                /** @var Media $media */
                if($media->hasField('field_image')){
                    $media_field_image = $media->get('field_image')->getValue();
                    $fid = $media_field_image[0]['target_id'];

                    $files[$fid]['media'] = $media;
                }
            }
        }

        return $files;
    }


    /**
     * @param $images
     * @param $max_width
     * @return array
     * @throws
     */
    public function resizeImages($images, $max_width)
    {
        $current_size = 0;
        $new_size = 0;
        $images_converted = 0;
        foreach($images as $element)
        {
            /** @var File $file */
            $file = $element['file'];
            $path = $element['path'];
            $t = isset($element['transparency']) && $element['transparency'];

            $current_size += filesize($path);
            list($new_width, $new_heigth) = $this->resize_image_to_width($max_width, $path, $t);
            $new_size += filesize($path);


            if(isset($element['media'])){
                /** @var Media $media */
                $media = $element['media'];
                $media_field_image = $media->get('field_image')->getValue();
                $media_field_image[0]['width'] = $new_width;
                $media_field_image[0]['height'] = $new_heigth;
                $media->set('field_image', $media_field_image);

                $thumbnail = $media->get('thumbnail')->getValue();
                $thumbnail[0]['width'] = $new_width;
                $thumbnail[0]['height'] = $new_heigth;
                $media->set('thumbnail', $thumbnail);
                $media->save();
            }

            $file->setSize(filesize($path));
            $file->save();

            $images_converted++;
        }

        $current_size = round($current_size / 1024 / 1024, 2);
        $new_size = round($new_size / 1024 / 1024, 2);

        return [$images_converted, $current_size, $new_size];
    }

    /**
     * @param $max_width
     * @param $filename
     * @param $transparency
     * @return array
     */
    private function resize_image_to_width($max_width, $filename, $transparency)
    {
        list($width, $height, $type) = getimagesize($filename);
        $image = $this->load_image($filename, $type);

        $ratio = $max_width / $width;
        $new_height = round($height * $ratio);

        $new_image = imagecreatetruecolor($max_width, $new_height);

        if($type === IMAGETYPE_PNG && $transparency){
            $color = imagecolorallocate($new_image, 255, 255, 255);
            imagefill($new_image, 0, 0, $color);
        }

        imagecopyresampled($new_image, $image, 0, 0, 0, 0, $max_width, $new_height, $width, $height);

        $this->save_image($new_image, $filename, $type);

        clearstatcache(true, $filename);

        return [$max_width, $new_height];
    }

    /**
     * @param $filename
     * @param $type
     * @return resource|null
     */
    private function load_image($filename, $type)
    {
        $image = null;

        switch($type){
            case IMAGETYPE_JPEG:
                $image = imagecreatefromjpeg($filename);
                break;
            case IMAGETYPE_PNG:
                $image = imagecreatefrompng($filename);
                break;
        }

        return $image;
    }

    /**
     * @param $image
     * @param $filename
     * @param $type
     * @param int $quality
     */
    private function save_image($image, $filename, $type, $quality = 100)
    {
        switch($type){
            case IMAGETYPE_JPEG:
                imagejpeg($image, $filename, $quality);
                break;
            case IMAGETYPE_PNG:
                imagepng($image, $filename);
                break;
        }

        imagedestroy($image);
    }

    /**
     * @param $original_file
     * @param $output_file
     * @param $quality
     */
    private function gd_png2jpg($original_file, $output_file, $quality = 100) {
        $image = $this->load_image($original_file, IMAGETYPE_PNG);
        $this->save_image($image, $output_file, IMAGETYPE_JPEG, $quality);
    }

    /**
     * @param $original_file
     * @param $output_file
     * @param $quality
     * @throws \ImagickException
     */
    private function imagick_png2jpg($original_file, $output_file, $quality = 100)
    {
        $im = new \Imagick();
        $im->readImage($original_file);
        $im = $im->flattenImages();
        $im->setCompressionQuality($quality);
        $im->setImageFormat('jpg');
        $im->writeImages($output_file, false);
        $im->clear();
    }

    /**
     * from https://stackoverflow.com/questions/5495275/how-to-check-if-an-image-has-transparency-using-gd
     *
     * @param $file
     * @return bool
     */
    private function detect_transparency($file)
    {
        if(!@getimagesize($file)) return false;

        if(ord(file_get_contents($file, false, null, 25, 1)) & 4) return true;

        $content = file_get_contents($file);
        if(stripos($content,'PLTE') !== false && stripos($content, 'tRNS') !== false) return true;

        return false;
    }

    public function getFid(FileInterface $file)
    {
        $fid = $file->get('fid')->getValue();

        return $fid[0]['value'];
    }

}
