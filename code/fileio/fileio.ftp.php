<?php

/**
 * FileIO FTP Remote Storage API
 *
 * Use remote hard disk space as a way to store image files (accessed via FTP), and provide a set of methods for the program to manage pictures
 *
 * @package PMCLibrary
 * @version $Id$
 * @date $Date$
 */
class FileIOftp extends AbstractIfsFileIO {

    var $conn, $parameter, $thumbLocalPath;

    /* Private Login FTP */
    private function _ftp_login() {
        if ($this->conn) {
            return true;
        }
        $this->conn = ftp_connect($this->parameter[0], $this->parameter[1]);
        if ($result = @ftp_login($this->conn, $this->parameter[2], $this->parameter[3])) {
            if ($this->parameter[4] == 'PASV') {
                ftp_pasv($this->conn, true);
            } // Passive mode
            ftp_set_option($this->conn, FTP_TIMEOUT_SEC, 120); // Extend Timeout to 120 seconds
            @ftp_chdir($this->conn, $this->parameter[5]);
        }
        return $result;
    }

    /**
     * Close FTP and save index files
     */
    private function _ftp_close() {
        if ($this->conn) {
            ftp_close($this->conn);
        }
    }

    public function __construct($parameter, $ENV) {
        parent::__construct($parameter, $ENV);

        register_shutdown_function(array($this, '_ftp_close')); // Set destructor (executed before PHP ends)
        set_time_limit(120); // Execution time 120 seconds (FTP transfers can be long)
        $this->thumbLocalPath = $ENV['THUMB']; // Location of the preview image
        $this->parameter = $parameter;
        /*
          [0] : FTP Server Location
          [1] : FTP Server Port Number
          [2] : FTP User Account
          [3] : FTP User Password
          [4] : Are you using passive mode? (PASV: Used, NOPASV: not used)
          [5] : FTP Default Working Directory
          [6] : Working directory corresponding URL
          [7] : Whether to upload the preview image remotely (true: yes, false: no, use local files)
         */
    }

    public function init() {
        return true;
    }

    public function deleteImage($imgname) {
        if (!$this->_ftp_login()) {
            return 0;
        }
        if (!is_array($imgname)) {
            $imgname = array($imgname);
        } // Single Name Parameter

        $size = 0;
        $size_perimg = 0;
        foreach ($imgname as $i) {
            $size_perimg = $this->getImageFilesize($i);
            if (!$this->parameter[7] && strpos($i, 's.') !== false) {
                @unlink($this->thumbLocalPath . $i);
            } else {
                if (!ftp_delete($this->conn, $i)) {
                    if ($this->remoteImageExists($this->parameter[6] . $i))
                        continue; // Cannot be deleted, the file exists (keep the index)

// Cannot be deleted, the file disappears (update index)
                }
            }
            $this->IFS->delRecord($i); // Delete from index
            $size += $size_perimg;
        }
        return $size;
    }

    public function uploadImage($imgname, $imgpath, $imgsize) {
        if (!$this->parameter[7] && strpos($imgname, 's.') !== false) {
            $this->IFS->addRecord($imgname, $imgsize, ''); // Added to the index
            return true; // Do not process preview images
        }
        if (!$this->_ftp_login()) {
            return false;
        }
        $result = ftp_put($this->conn, $imgname, $imgpath, FTP_BINARY);
        if ($result) {
            $this->IFS->addRecord($imgname, $imgsize, ''); // Added to the index
            unlink($imgpath); // Delete the local temporary storage after uploading
        }
        return $result;
    }

    public function getImageURL($imgname) {
        if (!$this->parameter[7] && strpos($imgname, 's.') !== false) {
            return $this->getImageLocalURL($imgname);
        }
        return $this->IFS->beRecord($imgname) ? $this->parameter[6] . $imgname : false;
    }
}