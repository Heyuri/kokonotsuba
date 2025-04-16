<?php

/**
 * FileIO Satellite Backend
 *
 * Use satellite.php/pl to manage image files using remote space
 *
 * @package PMCLibrary
 * @version $Id$
 * @date $Date$
 * @deprecated
 */
class FileIOsatellite extends AbstractIfsFileIO {

    var $userAgent, $parameter, $thumbLocalPath;

    /**
     * Test the connection and initialize the remote satellite host
     */
    public function init() {
        if (!($fp = @fsockopen($this->parameter[0]['host'], 80))) {
            return false;
        }

        $argument = 'mode=init&key=' . $this->parameter[2];
        $out = 'POST ' . $this->parameter[0]['path'] . " HTTP/1.1\r\n";
        $out .= 'Host: ' . $this->parameter[0]['host'] . "\r\n";
        $out .= 'User-Agent: ' . $this->userAgent . "\r\n";
        $out .= "Content-Type: application/x-www-form-urlencoded\r\n";
        $out .= 'Content-Length: ' . strlen($argument) . "\r\n\r\n";
        $out .= $argument;
        fwrite($fp, $out);
        $result = fgets($fp, 128); // One take is enough to get to the head of the file
        fclose($fp);

        return (strpos($result, '202 Accepted') !== false); // Check the status value to detect whether the transmission is successful
    }

    /**
     * Transmit the capture request to the remote satellite host
     */
    private function _transloadSatellite($imgname) {
        if (!($fp = @fsockopen($this->parameter[0]['host'], 80)))
            return false;
        $argument = 'mode=transload&key=' . $this->parameter[2] . '&imgurl=http:' . $this->getImageLocalURL($imgname) . '&imgname=' . $imgname;
        $out = 'POST ' . $this->parameter[0]['path'] . " HTTP/1.1\r\n";
        $out .= 'Host: ' . $this->parameter[0]['host'] . "\r\n";
        $out .= 'User-Agent: ' . $this->userAgent . "\r\n";
        $out .= "Content-Type: application/x-www-form-urlencoded\r\n";
        $out .= 'Content-Length: ' . strlen($argument) . "\r\n\r\n";
        $out .= $argument;
        fwrite($fp, $out);
        $result = fgets($fp, 128); // One take is enough to get to the head of the file
        fclose($fp);

        return (strpos($result, '202 Accepted') !== false); // Check whether the status value detects whether the transmission is successful
    }

    /**
     * Send files directly to the remote satellite host
     */
    private function _uploadSatellite($imgname, $imgpath) {
        srand((double) microtime() * 1000000);
        $boundary = '---------------------' . substr(md5(rand(0, 32000)), 0, 10); // Generate divider

        $argument = ''; // Temporary data storage
        // General field data conversion
        $formField = array('mode' => 'upload', 'key' => $this->parameter[2], 'imgname' => $imgname);
        foreach ($formField as $ikey => $ival) {
            $argument .= "--$boundary\r\n";
            $argument .= "Content-Disposition: form-data; name=\"" . $ikey . "\"\r\n\r\n";
            $argument .= $ival . "\r\n";
            $argument .= "--$boundary\r\n";
        }
        // Upload file field data conversion
        $imginfo = getimagesize($imgpath); // Get image information
        $argument .= "--$boundary\r\n";
        $argument .= 'Content-Disposition: form-data; name="imgfile"; filename="' . $imgname . '"' . "\r\n";
        $argument .= 'Content-Type: ' . $imginfo['mime'] . "\r\n\r\n";
        $argument .= join('', file($imgpath)) . "\r\n";
        $argument .= "--$boundary--\r\n";

        $out = 'POST ' . $this->parameter[0]['path'] . " HTTP/1.1\r\n";
        $out .= 'Host: ' . $this->parameter[0]['host'] . "\r\n";
        $out .= 'User-Agent: ' . $this->userAgent . "\r\n";
        $out .= "Content-Type: multipart/form-data, boundary=$boundary\r\n";
        $out .= 'Content-Length: ' . strlen($argument) . "\r\n\r\n";
        $out .= $argument;

        if (!($fp = @fsockopen($this->parameter[0]['host'], 80))) {
            return false;
        }
        fwrite($fp, $out);
        $result = fgets($fp, 128);
        fclose($fp);

        return (strpos($result, '202 Accepted') !== false);
    }

    /**
     * Send an image removal request
     */
    private function _deleteSatellite($imgname) {
        if (!($fp = @fsockopen($this->parameter[0]['host'], 80))) {
            return false;
        }

        $argument = 'mode=delete&key=' . $this->parameter[2] . '&imgname=' . $imgname;
        $out = 'POST ' . $this->parameter[0]['path'] . " HTTP/1.1\r\n";
        $out .= 'Host: ' . $this->parameter[0]['host'] . "\r\n";
        $out .= 'User-Agent: ' . $this->userAgent . "\r\n";
        $out .= "Content-Type: application/x-www-form-urlencoded\r\n";
        $out .= 'Content-Length: ' . strlen($argument) . "\r\n\r\n";
        $out .= $argument;
        fwrite($fp, $out);
        $result = fgets($fp, 128);
        fclose($fp);

        return (strpos($result, '202 Accepted') !== false);
    }

    public function __construct($parameter, $ENV) {
        parent::__construct($parameter, $ENV);

        set_time_limit(120); // Execution time 120 seconds (transfer can be long)
        $this->userAgent = 'Mozilla/4.0 (compatible; MSIE 8.0; Windows NT 5.1)'; // Just for fun ;-)
        $this->thumbLocalPath = $ENV['THUMB']; // The location of the preview image
        $this->parameter = $parameter; // Reparse the parameters
        $this->parameter[0] = parse_url($this->parameter[0]); // URL location disassembly
        /*
          [0] : Satellite program remote URL location
          [1] : Whether to use the Transload method to request the satellite program to grab the image file (true: yes false: no, use traditional HTTP upload)
          [2] : Transfer authentication key
          [3] : The URL corresponding to the remote directory
          [4] : Whether the preview image is uploaded to the remote location (true: yes, false: no, use local files)
         */
    }

    public function deleteImage($imgname) {
        if (!is_array($imgname))
            $imgname = array($imgname); // Single name parameter

        $size = 0;
        $size_perimg = 0;
        foreach ($imgname as $i) {
            $size_perimg = $this->getImageFilesize($i);
            if (!$this->parameter[4] && strpos($i, 's.') !== false) {
                @unlink($this->thumbLocalPath . $i);
            } else {
                // Delete error occurred
                if (!$this->_deleteSatellite($i)) {
                    if ($this->remoteImageExists($this->parameter[3] . $i))
                        continue; // Cannot be deleted, the file exists (keep the index)

// Cannot be deleted, the file disappears (update index)
                }
            }
            $this->IFS->delRecord($i);
            $size += $size_perimg;
        }
        return $size;
    }

    public function uploadImage($imgname, $imgpath, $imgsize) {
        if (!$this->parameter[4] && strpos($imgname, 's.') !== false) {
            $this->IFS->addRecord($imgname, $imgsize, '');
            return true; // Preview image is not processed
        }
        $result = $this->parameter[1]
                ? $this->_transloadSatellite($imgname)
                : $this->_uploadSatellite($imgname, $imgpath);
        if ($result) {
            $this->IFS->addRecord($imgname, $imgsize, ''); // Added to the index
            unlink($imgpath); // After uploading, delete the local temporary storage
        }
        return $result;
    }

    public function getImageURL($imgname) {
        if (!$this->parameter[4] && strpos($imgname, 's.') !== false) {
            return $this->getImageLocalURL($imgname);
        }
        return $this->IFS->beRecord($imgname) ? $this->parameter[3] . $imgname : false;
    }
}
