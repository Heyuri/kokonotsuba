<?php
/* imageMeta module : image reverse searching and exif information (Pre-Alpha)
 * $Id$
 * exif.php from http://www.rjk-hosting.co.uk/programs/prog.php?id=4
 */

namespace Kokonotsuba\Modules\imageMeta;

use Kokonotsuba\ModuleClasses\abstractModuleMain;
use PMCLibrary;

class moduleMain extends abstractModuleMain {
	private $enable_exif, $enable_imgops, $enable_iqdb, $enable_swfchan = false; // Initialize options, actually defined in config files

	private $myPage;
	
	public function initialize(): void {
		$this->enable_exif = $this->getConfig('ModuleSettings.EXIF_DATA_VIEWER');
		$this->enable_imgops = $this->getConfig('ModuleSettings.IMG_OPS');
		$this->enable_iqdb = $this->getConfig('ModuleSettings.IQDB');
		$this->enable_swfchan = $this->getConfig('ModuleSettings.SWFCHAN');

		$this->moduleContext->moduleEngine->addListener('Post', function (&$arrLabels, $post, $threadPosts, $board) {
			$this->onRenderPost($arrLabels, $post, $board);
		});
	}

	public function getName(): string {
		return 'image meta info and reverse searcher';
	}

	public function getVersion(): string {
		return 'Koko BBS Release 1';
	}

	public function onRenderPost(&$arrLabels, $post, $board): void {
		if(!isset($arrLabels['{$IMG_BAR}'])) {
			return;
		}
	
		$FileIO = PMCLibrary::getFileIOInstance();
		
		if($post['imgw'] + $post['imgh'] === 0) return;
		
		$file = $post['tim'] . $post['ext'];
	
		static $nonReverseSearchableExtensions = ['.swf', '.mp4', '.webm'];
		$ext = strtolower($post['ext']);
		$isNotAReverseSearchableImage = in_array($ext, $nonReverseSearchableExtensions);
		$isSwf = $ext === '.swf';
	
		// Check once if the image exists, to avoid repeated checks
		$imageExists = $FileIO->imageExists($file, $board);
		
		if (!$imageExists) {
			// If the image does not exist, skip processing
			return;
		}

		// Prepare HTML parts for appending
		$imgBarHtml = '';
	
		// EXIF
		if ($this->enable_exif) {
			$imgBarHtml .= '<span class="exifLink imageOptions">[<a href="' . $this->myPage . '&file=' . $file . '">EXIF</a>]</span> ';
		}
	
		// ImgOps
		if ($this->enable_imgops && !$isNotAReverseSearchableImage) {
			$imgBarHtml .= '<span class="imgopsLink imageOptions">[<a href="http://imgops.com/' . $FileIO->getImageURL($file, $board) . '" target="_blank">ImgOps</a>]</span> ';
		}
	
		// Anime/manga search engine (iqdb)
		if ($this->enable_iqdb && !$isNotAReverseSearchableImage) {
			$imgBarHtml .= '<span class="iqdbLink imageOptions">[<a href="http://iqdb.org/?url=' . $FileIO->getImageURL($file, $board) . '" target="_blank">iqdb</a>]</span> ';
		}
	
		// SWFChan archive
		if ($this->enable_swfchan && $isSwf) {
			$rawByteFileSize = $FileIO->getImageFilesize($file, $board);

	
			$imgBarHtml .= '<span class="swfchanLink imageOptions">[<a href="http://eye.swfchan.com/search/?q=>' . $rawByteFileSize . '" target="_blank">swfchan</a>]</span> ';
		}

		// Append the built HTML content to the final label
		$arrLabels['{$IMG_BAR}'] .= $imgBarHtml;
	}	
	

	public function ModulePage(){
		$FileIO = PMCLibrary::getFileIOInstance();
		
		$boardUID = $_GET['boardUID'] ?? $this->moduleContext->board->getBoardUID();
		$board = $this->moduleContext->boardService->getBoard($boardUID);
		$boardConfig = $board->loadBoardConfig();
		

		echo $this->moduleContext->board->getBoardHead('Image meta');

		$file = $_GET['file'] ?? '';
		echo '[<a href="' . $this->getConfig('STATIC_INDEX_FILE') . '">Return</a>]';
		echo '<ul class="exifInfoList">';
		if($file && $FileIO->imageExists($file, $board)){
			$pfile = $board->getBoardUploadedFilesDirectory().$boardConfig['IMG_DIR'].'/'.$file;
			if(function_exists("exif_read_data")) {
				echo "<li>DEBUG: Using exif_read_data()</li>";
				$exif_data = exif_read_data($pfile, 0, true);

				if(is_array($exif_data) && count($exif_data)) {
					echo '<li>Image contains EXIF data:</li>';
					echo '</ul><table class="exif postlists"><tbody>';
					foreach($exif_data as $key=>$section) {
						foreach($section as $name=>$value) {
							echo "<tr><th>$key.$name</th><td>$value</td></tr>";
						}
					}
					echo '</tbody></table><p>';
				} else {
					echo '<li>No EXIF data found.</li>';
				}
			} else {
				echo "<li>DEBUG: Using built-in exif library</li>";
				$exif=new exif($pfile);
				if(count($exif->exif_data)) {
					echo '<li>Image contains EXIF data:</li>';
					echo '</ul><table border="1"><tbody>';
					foreach($exif->exif_data as $key=>$value) {
						echo "<tr><th>$key</th><td>$value</td></tr>";
					}
					echo '</tbody></table><p>';
				} else {
					echo '<li>No EXIF data found.</li>';
				}
			}
		} else {
			echo '<li><strong class="error">File Not Found!</strong></li>';
		}
		echo '</ul>';
		if(isset($_SERVER['HTTP_REFERER'])) echo '[<a href="'.$_SERVER['HTTP_REFERER'].'" onclick="event.preventDefault();history.go(-1);">Back</a>]';
		echo $this->moduleContext->board->getBoardFooter();
	}
}

class exif{
/*  Exif reader v 1.2
    By Richard James Kendall 
    Bugs to richard@richardjameskendall.com 
    Free to use, please acknowledge me 
*/
	// holds the formatted data read from the EXIF data area
	var $exif_data = array();

	// holds the number format used in the EXIF data (1 == moto, 0 == intel)
	var $align;

	// holds the lengths and names of the data formats
	var $format_length = array(0, 1, 1, 2, 4, 8, 1, 1, 2, 4, 8, 4, 8);
	var $format_type = array("", "BYTE", "STRING", "USHORT", "ULONG", "URATIONAL", "SBYTE", "UNDEFINED", "SSHORT", "SLONG", "SRATIONAL", "SINGLE", "DOUBLE");

	// data for EXIF enumeations
	var $Orientation = array("", "Normal (0 deg)", "Mirrored", "Upsidedown", "Upsidedown & Mirrored", "Mirror horizontal and rotate 270 CW","Rotate 90 CW","Mirror horizontal and rotate 90 CW","Rotate 270 CW");
	var $ResUnit = array("", "inches", "inches", "cm", "mm", "um");
	var $YCbCrPos = array("", "Centre of Pixel Array", "Datum Points");
	var $ExpProg = array("", "Manual", "Program", "Apeture Priority", "Shutter Priority", "Program Creative", "Program Action", "Portrait", "Landscape");
	var $LightSource = array("Unknown", "Daylight", "Fluorescent", "Tungsten (incandescent)", "Flash", "Fine Weather", "Cloudy Weather", "Share", "Daylight Fluorescent", "Day White Fluorescent", "Cool White Fluorescent", "White Fluorescent", "Standard Light A", "Standard Light B", "Standard Light C", "D55", "D65", "D75", "D50", "ISO Studio Tungsten");
	var $MeterMode = array("Unknown", "Average", "Centre Weighted", "Spot", "Multi-Spot", "Pattern", "Partial");
	var $RenderingProcess = array("Normal Process", "Custom Process");
	var $ExposureMode = array("Auto", "Manual", "Auto Bracket");
	var $WhiteBalance = array("Auto", "Manual");
	var $SceneCaptureType = array("Standard", "Landscape", "Portrait", "Night Scene");
	var $GainControl = array("None", "Low Gain Up", "High Gain Up", "Low Gain Down", "High Gain Down");
	var $Contrast = array("Normal", "Soft", "Hard");
	var $Saturation = array("Normal", "Low Saturation", "High Saturation");
	var $Sharpness = array("Normal", "Soft", "Hard");
	var $SubjectDistanceRange = array("Unknown", "Macro", "Close View", "Distant View");
	var $FocalPlaneResUnit = array("", "inches", "inches", "cm", "mm", "um");
	var $SensingMethod = array("", "Not Defined", "One-chip Colour Area Sensor", "Two-chip Colour Area Sensor", "Three-chip Colour Area Sensor", "Colour Sequential Area Sensor", "Trilinear Sensor", "Colour Sequential Linear Sensor");
	var $CalibrationIlluminant1,$CalibrationIlluminant2;
	var $Flash = array(0x0 => "No Flash", 0x1 => "Fired", 0x5 => "Fired, Return not detected", 0x7 => "Fired, Return detected", 0x8 => "On, Did not fire", 0x9 => "On", 0xd => "On, Return not detected", 0xf => "On, Return detected", 0x10 => "Off", 0x14 => "Off, Did not fire, Return not detected", 0x18 => "Auto, Did not fire", 0x19 => "Auto, Fired", 0x1d => "Auto, Fired, Return not detected", 0x1f => "Auto, Fired, Return detected", 0x20 => "No flash function", 0x30 => "Off, No flash function", 0x41 => "Fired, Red-eye reduction", 0x45 => "Fired, Red-eye reduction, Return not detected", 0x47 => "Fired, Red-eye reduction, Return detected", 0x49 => "On, Red-eye reduction", 0x4d => "On, Red-eye reduction, Return not detected", 0x4f => "On, Red-eye reduction, Return detected", 0x50 => "Off, Red-eye reduction", 0x58 => "Auto, Did not fire, Red-eye reduction", 0x59 => "Auto, Fired, Red-eye reduction", 0x5d => "Auto, Fired, Red-eye reduction, Return not detected", 0x5f => "Auto, Fired, Red-eye reduction, Return detected");

	// gets one byte from the file at handle $fp and converts it to a number
	function fgetord($fp) {
		return ord(fgetc($fp));
	}

	function gcd($a, $b) {
		if ($a < $b) {
			$gcd = $this->gcd($b, $a);
		}else{
			assert($a > 0);
			assert($b >= 0);
			while ($b != 0) {
				$t = $a % $b;
				$a = $b;
				$b = $t;
			}
			$gcd = $a;
		}
		return $gcd;
	}

	function fractionSimply($n,$d) {
		$g=($n>-1 && $n > -1)?$this->gcd($n, $d):1;
		return array($n/$g,$d/$g);
	}

	function fractionToMixed($n,$d) {
		$m = $n % $d;
		return array(($n-$m)/$d,$m,$d);
	}

	// takes $data and pads it from the left so strlen($data) == $shouldbe
	function pad($data, $shouldbe, $put) {
		if (strlen($data) == $shouldbe) {
			return $data;
		} else {
			$padding = "";
			for ($i = strlen($data);$i < $shouldbe;$i++) {
				$padding .= $put;
			}
			return $padding . $data;
		}
	}

	// converts a number from intel (little endian) to motorola (big endian format)
	function ii2mm($intel) {
		$mm = "";
		for ($i = 0;$i <= strlen($intel);$i+=2) {
			$mm .= substr($intel, (strlen($intel) - $i), 2);
		}
		return $mm;
	}

	// gets a number from the EXIF data and converts if to the correct representation
	function getnumber($data, $start, $length, $align) {
		$a = bin2hex(substr($data, $start, $length));
		if (!$align) {
			$a = $this->ii2mm($a);
		}
		return hexdec($a);
	}

	// gets a rational number (num, denom) from the EXIF data and produces a decimal
	function getrational($data, $align, $type) {
		$a = bin2hex($data);
		if (!$align) {
			$a = $this->ii2mm($a);
		}
		if ($align == 1) {
			$n = hexdec(substr($a, 0, 8));
			$d = hexdec(substr($a, 8, 8));
		} else {
			$d = hexdec(substr($a, 0, 8));
			$n = hexdec(substr($a, 8, 8));
		}
		if ($type == "S" && $n > 2147483647) {
			$n = $n - 4294967296;
		}
		if ($n == 0) {
			return 0;
		}
		if ($d != 0) {
			$ra=$this->fractionSimply($n,$d);
			return ($n / $d).($ra[1]!=1&&$ra[1]!=$d?' ('.$ra[0]."/".$ra[1].')':'').($d!=1?' ['.$n."/".$d.']':'');
		} else {
			return $n."/".$d;
		}
	}

	// opens the JPEG file and attempts to find the EXIF data
	function exif($file) {
		$this->CalibrationIlluminant1=$this->CalibrationIlluminant2=$this->LightSource;
		$this->align=0;

		$fp = fopen($file, "rb");
		$a = $this->fgetord($fp);
		if ($a != 255 || $this->fgetord($fp) != 216) {
			return false;
		}
		$ef = false;
		while (!feof($fp)) {
			$section_length = 0;
			$section_marker = 0;
			$lh = 0;
			$ll = 0;
			for ($i = 0;$i < 7;$i++) {
				$section_marker = $this->fgetord($fp);
				if ($section_marker != 255) {
					break;
				}
				if ($i >= 6) {
					return false;
				}
			}
			if ($section_marker == 255) {
				return false;
			}
			$lh = $this->fgetord($fp);
			$ll = $this->fgetord($fp);
			$section_length = ($lh << 8) | $ll;
			$data = chr($lh) . chr($ll);
			$t_data = fread($fp, $section_length - 2);
			$data .= $t_data;
			switch ($section_marker) {
				case 225:
					fclose($fp);
					return $this->extractEXIFData(substr($data, 2), $section_length);
					$ef = true;
					break;
			}
		}
		fclose($fp);
	}

	// reads the EXIF header and if it is intact it calls readEXIFDir to get the data
	function extractEXIFData($data, $length) {
		if (substr($data, 0, 4) == "Exif") {
			if (substr($data, 6, 2) == "II") {
				$this->align = 0;
			} else {
				if (substr($data, 6, 2) == "MM") {
					$this->align = 1;
				} else {
					return false;
				}
			}
			$a = $this->getnumber($data, 8, 2, $this->align);
			if ($a != 0x2a) {
				return false;
			}
			$first_offset = $this->getnumber($data, 10, 4, $this->align);
			if ($first_offset < 8 || $first_offset > 16) {
				return false;
			}
			$this->readEXIFDir(substr($data, 14), 8, $length - 6);
			return true;
		} else {
			return false;
		}
	}

	// takes an EXIF tag id and returns the string name of that tag
	function tagid2name($id) {
		switch ($id) {
			case 0x000b: return "ACDComment"; break;
			case 0x00fe: return "ImageType"; break;
			case 0x0106: return "PhotometicInterpret"; break;
			case 0x010e: return "ImageDescription"; break;
			case 0x010f: return "Make"; break;
			case 0x0110: return "Model"; break;
			case 0x0112: return "Orientation"; break;
			case 0x0115: return "SamplesPerPixel"; break;
			case 0x011a: return "XRes"; break;
			case 0x011b: return "YRes"; break;
			case 0x011c: return "PlanarConfig"; break;
			case 0x0128: return "ResUnit"; break;
			case 0x0131: return "Software"; break;
			case 0x0132: return "DateTime"; break;
			case 0x013b: return "Artist"; break;
			case 0x013f: return "WhitePoint"; break;
			case 0x0211: return "YCbCrCoefficients"; break;
			case 0x0213: return "YCbCrPos"; break;
			case 0x0214: return "RefBlackWhite"; break;
			case 0x1000: return "RelatedImageFileFormat"; break;
			case 0x1001: return "RelatedImageWidth"; break;
			case 0x1002: return "RelatedImageLength"; break;
			case 0x8298: return "Copyright"; break;
			case 0x829a: return "ExposureTime"; break;
			case 0x829d: return "FNumber"; break;
			case 0x8822: return "ExpProg"; break;
			case 0x8825: return "GPSInfo"; break;
			case 0x8827: return "ISOSpeedRating"; break;
			case 0x9003: return "DTOpticalCapture"; break;
			case 0x9004: return "DTDigitised"; break;
			case 0x9102: return "CompressedBitsPerPixel"; break;
			case 0x9201: return "ShutterSpeed"; break;
			case 0x9202: return "ApertureWidth"; break;
			case 0x9203: return "Brightness"; break;
			case 0x9204: return "ExposureBias"; break;
			case 0x9205: return "MaxApetureWidth"; break;
			case 0x9206: return "SubjectDistance"; break;
			case 0x9207: return "MeterMode"; break;
			case 0x9208: return "LightSource"; break;
			case 0x9209: return "Flash"; break;
			case 0x920a: return "FocalLength"; break;
			case 0x920b: return "FlashEnergy"; break;
			case 0x9213: return "ImageHistory"; break;
			case 0x9214: return "SubjectLocation"; break;
			case 0x9217: return "SensingMethod"; break;
			case 0x927c: return "MakerNote"; break;
			case 0x9286: return "UserComment"; break;
			case 0x9290: return "SubsecTime"; break;
			case 0x9291: return "SubsecTimeOrig"; break;
			case 0x9292: return "SubsecTimeDigi"; break;
			case 0x9c9b: return "XPTitle"; break;
			case 0x9c9c: return "XPComment"; break;
			case 0x9c9d: return "XPAuthor"; break;
			case 0x9c9e: return "XPKeywords"; break;
			case 0x9c9f: return "XPSubject"; break;
			case 0xa000: return "FlashPixVersion"; break;
			case 0xa001: return "ColourSpace"; break;
			case 0xa002: return "ImageWidth"; break;
			case 0xa003: return "ImageHeight"; break;
			case 0xa005: return "InteropOffset"; break;
			case 0xa20e: return "FocalPlaneXRes"; break;
			case 0xa20f: return "FocalPlaneYRes"; break;
			case 0xa210: return "FocalPlaneResUnit"; break;
			case 0xa214: return "SubjectLocation"; break;
			case 0xa217: return "SensingMethod"; break;
			case 0xa300: return "ImageSource"; break;
			case 0xa301: return "SceneType"; break;
			case 0xa401: return "RenderingProcess"; break;
			case 0xa402: return "ExposureMode"; break;
			case 0xa403: return "WhiteBalance"; break;
			case 0xa404: return "DigitalZoomRatio"; break;
			case 0xa405: return "FocalLength35mm"; break;
			case 0xa406: return "SceneCaptureType"; break;
			case 0xa407: return "GainControl"; break;
			case 0xa408: return "Contrast"; break;
			case 0xa409: return "Saturation"; break;
			case 0xa40a: return "Sharpness"; break;
			case 0xa40c: return "SubjectDistanceRange"; break;
			case 0xa500: return "Gamma"; break;
			case 0xbc02: return "Transfomation"; break;
			case 0xc65a: return "CalibrationIlluminant1"; break;
			case 0xc65b: return "CalibrationIlluminant2"; break;
			default: return "(0x".dechex($id).")"; break;
		}
	}

	// takes a (U/S)(SHORT/LONG) checks if an enumeration for this value exists and if it does returns the enumerated value for $tvalue
	function enumvalue($tname, $tvalue) {
		if (isset($this->$tname)) {
			$tmp = $this->$tname;
			return $tmp[$tvalue];
		} else {
			return $tvalue;
		}
	}

	// takes a tag id along with the format, data and length of the data and deals with it appropriatly
	function dealwithtag($tag, $format, $data, $length, $align) {
		$w = false;
		$val = "";
		switch ($this->format_type[$format]) {
			case "STRING":
				$val = trim(substr($data, 0, $length));
				$w = true;
				break;
			case "ULONG":
			case "SLONG":
				$val = $this->enumvalue($this->tagid2name($tag), $this->getnumber($data, 0, 4, $align));
				$w = true;
				break;
			case "USHORT":
			case "SSHORT":
				switch ($tag) {
					case 0x9214:
						
						break;
					case 0xa001:
						$tmp = $this->getnumber($data, 0, 2, $align);
						if ($tmp == 1) {
							$val = "sRGB";
							$w = true;
						} else if ($tmp == 2) {
							$val = "Adobe RGB";
							$w = true;
						} else {
							$val = "Uncalibrated";
							$w = true;
						}
						break;
					default:
						$val = $this->enumvalue($this->tagid2name($tag), $this->getnumber($data, 0, 2, $align));
						$w = true;
						break;
				} 
				break;
			case "URATIONAL":
				$val = $this->getrational(substr($data, 0, 8), $align, "U");
				$w = true;
				break;
			case "SRATIONAL":
				$val = $this->getrational(substr($data, 0, 8), $align, "S");
				$w = true;
				break;
			case "UNDEFINED":
				switch ($tag) {
					case 0xa300:
						$tmp = $this->getnumber($data, 0, 2, $align);
						if ($tmp == 3) {
							$val = "Digital Camera";
							$w = true;
						} else {
							$val = "Unknown";
							$w = true;
						}
						break;
					case 0xa301:
						$tmp = $this->getnumber($data, 0, 2, $align);
						if ($tmp == 3) {
							$val = "Directly Photographed";
							$w = true;
						} else {
							$val = "Unknown";
							$w = true;
						}
						break;
				}
				break;
		}
		if ($w) {
			$this->exif_data[$this->tagid2name($tag)] = $val;
		}
	}

	// reads the tags from and EXIF IFD and if correct deals with the data
	function readEXIFDir($data, $offset_base, $exif_length) {
		$value_ptr = 0;
		$sofar = 2;
		$data_in = "";
		$number_dir_entries = $this->getnumber($data, 0, 2, $this->align);
		for ($i = 0;$i < $number_dir_entries;$i++) {
			$sofar += 12;
			$dir_entry = substr($data, 2 + 12 * $i);
			$tag = $this->getnumber($dir_entry, 0, 2, $this->align);
			$format = $this->getnumber($dir_entry, 2, 2, $this->align);
			$components = $this->getnumber($dir_entry, 4, 4, $this->align);
			if (($format - 1) >= 12) {
				return false;
			}
			$byte_count = $components * $this->format_length[$format];
			if ($byte_count > 4) {
				$offset_val = ($this->getnumber($dir_entry, 8, 4, $this->align)) - $offset_base;
				if (($offset_val + $byte_count) > $exif_length) {
					return false;
				}
				$data_in = substr($data, $offset_val);
			} else {
				$data_in = substr($dir_entry, 8);
			}
			if ($tag == 0x8769) {
				$tmp = ($this->getnumber($data_in, 0, 4, $this->align)) - 8;
				$this->readEXIFDir(substr($data, $tmp), $tmp + 8 , $exif_length);
			} else {
				$this->dealwithtag($tag, $format, $data_in, $byte_count, $this->align);
			}
		}
	}
}//End-Of-Module
