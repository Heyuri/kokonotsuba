<?php

/**
 * FileIO Local local storage API (Without IFS index cache)
 *
 * Use the local hard disk space as the image file storage method, and provide a set of methods for the program to manage images
 *
 * This version reverts to the behavior of the old version (5th.Release), and still uses file I/O to confirm when judging image files,
 * Avoid the problem that the image file cannot be displayed due to an error in IFS in a specific environment.
 *
 * @package PMCLibrary
 * @version $Id$
 * @date $Date$
 * @since 8th.Release
 */
class FileIOlocal extends AbstractFileIO {
    var $imgPath, $thumbPath;

    public function __construct($parameter, $ENV) {
        parent::__construct();

        $this->imgPath = $ENV['IMG'];
        $this->thumbPath = $ENV['THUMB'];
    }

    public function init() {
        return true;
    }

    public function imageExists($imgname) {
        return file_exists($this->getImagePhysicalPath($imgname));
    }

    public function deleteImage($imgname) {
        if (!is_array($imgname)) {
            $imgname = array($imgname); // Single name parameter
        }

        $size = 0;
        $size_perimg = 0;
        foreach ($imgname as $i) {
            $size_perimg = $this->getImageFilesize($i);
            if (unlink($this->getImagePhysicalPath($i))) {
                $size += $size_perimg;
            } 
        }
        return $size;
    }

    private function getImagePhysicalPath($imgname) {
        return (strpos($imgname, 's.') !== false ? $this->thumbPath : $this->imgPath) . $imgname;
    }

    public function uploadImage($imgname, $imgpath, $imgsize) {
        return false;
    }

    public function getImageFilesize($imgname) {
        $size = filesize($this->getImagePhysicalPath($imgname));
        if ($size === false) {
            $size = 0;
        }
        return $size;
    }

    public function getImageURL($imgname) {
        return $this->getImageLocalURL($imgname);
    }

    public function resolveThumbName($thumbPattern) {
        $shortcut = $this->resolveThumbNameShortcut($thumbPattern);
        if ($shortcut !== false) {
            return $shortcut;
        }

        $find = glob($this->thumbPath . $thumbPattern . 's.*');
        return ($find !== false && count($find) != 0) ? basename($find[0]) : false;
    }

    /**
     * Use the traditional 1234567890123s.jpg rules try to find the preview image, if you are lucky, you only need to find it once.
     *
     * @param string $thumbPattern preview image file name
     * @return bool found
     */
    private function resolveThumbNameShortcut($thumbPattern) {
        $shortcutFind = $this->getImagePhysicalPath($thumbPattern . 's.jpg');
        if (file_exists($shortcutFind)) {
            return basename($shortcutFind);
        } else {
            return false;
        }
    }

    protected function getCurrentStorageSizeNoCache() {
        $totalSize = 0;
        $dirs = array(
            new RecursiveDirectoryIterator($this->imgPath),
            new RecursiveDirectoryIterator($this->thumbPath)
        );
        
        foreach ($dirs as $dir) {
            $totalSize += $this->getDirectoryTotalSize($dir);
        }
        return $totalSize;
    }

    private function getDirectoryTotalSize($dirIterator) {
        $dirSize = 0;
        foreach (new RecursiveIteratorIterator($dirIterator) as $file) {
            $dirSize += $file->getSize();
        }
        return $dirSize;
    }
}