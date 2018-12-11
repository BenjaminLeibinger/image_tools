<?php

namespace Drupal\image_tools\Services;

use Drupal\Core\File\FileSystem;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Database\Connection;
use Drupal\file\Entity\File;
use Drupal\media_entity\Entity\Media;

const DEFAULT_MAX_WIDTH = 2048;

class ImageService {

    /** @var FileSystem */
    private $filesystem;

    /** @var EntityTypeManager */
    private $entityManager;

    /** @var Connection */
    private $db;

    /**
     * DrushImageCommandsCommands constructor.
     * @param FileSystem $filesystem
     * @param EntityTypeManager $entityManager
     * @param Connection $db
     */
    public function __construct(FileSystem $filesystem, EntityTypeManager $entityManager, Connection $db)
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

            $files[$image_path] = ['file' => $file, 'transparency' => $t];
        }

        return $files;
    }


    /**
     * Convert all Images with type png to jpg.
     *
     * @throws
     * @return int
     */
    public function convertPngToJpeg()
    {
        $files = $this->loadPngImages();

        $images_converted = 0;
        foreach($files as $path => $element)
        {
            if($element['transparency']){continue;}

            /** @var File $file */
            $file = $element['file'];

            $image_path = dirname($path);
            $image_name_jpg = preg_replace('"\.png$"', '.jpg', $file->getFilename());
            $image_path_jpg = $image_path . DIRECTORY_SEPARATOR . $image_name_jpg;

            if (extension_loaded('imagick')){
                $this->imagick_png2jpg($path, $image_path_jpg);
            }else{
                $this->gd_png2jpg($path, $image_path_jpg);
            }

            $file->setFileUri(preg_replace('"\.png$"', '.jpg', $file->getFileUri()));
            $file->setFilename($image_name_jpg);
            $file->setMimeType('image/jpeg');

            $file->save();
            unlink($path);

            $images_converted++;
        }

        return $images_converted;
    }


    /**
     * @param $max_width
     * @param $include_png
     * @return array
     * @throws
     */
    public function loadLargeImages($max_width, $include_png)
    {
        $png_type = $include_png ?  " or filemime = 'image/png'" : "";

        $iids = $this->db->query("SELECT fid, entity_id 
                                  FROM file_managed INNER JOIN media__field_image ON field_image_target_id = fid 
                                  WHERE field_image_width > :max_width and (filemime = 'image/jpeg' $png_type )",
            ['max_width' => $max_width]
        )->fetchAllAssoc('entity_id');

        $media_type_storage = $this->entityManager->getStorage('media');
        $file_storage = $this->entityManager->getStorage('file');

        $media_images = $media_type_storage->loadMultiple(array_keys($iids));
        $image_files = $file_storage->loadMultiple(array_column($iids, 'fid'));

        $files = [];
        foreach($media_images as $media) {
            /** @var Media $media */
            $media_field_image = $media->get('field_image')->getValue();
            /** @var File $file */
            $file = $image_files[$media_field_image[0]['target_id']];

            $image_path = $this->filesystem->realpath($file->getFileUri());

            $files[$image_path] = ['media' => $media, 'file' => $file];

            if($file->getMimeType() === 'image/png'){
                $t = $this->detect_transparency($image_path);
                $files[$image_path]['transparency'] = $t;
            }
        }

        return $files;
    }


    /**
     * @param $max_width
     * @param $include_png
     * @throws
     * @return int
     */
    public function resizeImages($max_width, $include_png)
    {
        $elements = $this->loadLargeImages($max_width, $include_png);

        $images_converted = 0;
        foreach($elements as $path => $element)
        {
            /** @var Media $media */
            $media = $element['media'];
            /** @var File $file */
            $file = $element['file'];

            $t = isset($element['transparency']) && $element['transparency'];

            list($new_width, $new_heigth) = $this->resize_image_to_width($max_width, $path, $t);

            $media_field_image = $media->get('field_image')->getValue();
            $media_field_image[0]['width'] = $new_width;
            $media_field_image[0]['height'] = $new_heigth;
            $media->set('field_image', $media_field_image);

            $thumbnail = $media->get('thumbnail')->getValue();
            $thumbnail[0]['width'] = $new_width;
            $thumbnail[0]['height'] = $new_heigth;
            $media->set('thumbnail', $thumbnail);

            $media->save();

            $file->setSize(filesize($path));
            $file->save();

            $images_converted++;
        }

        return $images_converted;
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
        $new_height = $height * $ratio;

        $new_image = imagecreatetruecolor($max_width, $new_height);

        if($type === IMAGETYPE_PNG && $transparency){
            $color = imagecolorallocate($new_image, 255, 255, 255);
            imagefill($new_image, 0, 0, $color);
        }

        imagecopyresampled($new_image, $image, 0, 0, 0, 0, $max_width, $new_height, $width, $height);

        $this->save_image($new_image, $filename, $type);

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

}
