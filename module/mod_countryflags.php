<?php

require_once("geoip/geoip2.phar");
use GeoIp2\Database\Reader;

class mod_countryflags  extends ModuleHelper {
	
	public function __construct($PMS) {
		parent::__construct($PMS);
	}

	public function getModuleName(){
		return 'mod_countryflags';
	}

	public function getModuleVersionInfo(){
		return '7th dev v140606';
	}

	private function _isgTLD($last,$add='') {
		$gtld = array('biz','com','info','name','net','org','pro','aero','asia','cat','coop','edu','gov','int','jobs','mil','mobi','museum','tel','travel','xxx');
		if(is_array($add)) {
			foreach($add as $a) {
				array_unshift($gtld,$a);
			}
		}
		foreach($gtld as $tld) {
			if($last == $tld) {
				return true;
			}
		}
		return false;
	}
	
	/*
	Toggle flag button for heyuri/jp/
	public function autoHookHead(&$file){
		if (preg_match('/\/jp\//i' ,$_SERVER['REQUEST_URI'])) {
			$file.= '<script type="text/javascript">function toggleFlagJP() {var field = document.getElementById("email"); if (!field.value.includes("flag")) field.value += "flag"; else field.value = field.value.replaceAll("flag", ""); }</script>';
		} 
	}
	
	public function autoHookPostForm(&$file){
		if (preg_match('/\/jp\//i' ,$_SERVER['REQUEST_URI'])) {
			$file.= '<nobr>[<a href="#" onclick="toggleFlagJP()">Toggle flag</a>]</nobr>';
		} 
	}
	*/
	
	public function autoHookThreadPost(&$arrLabels, $post, $isReply){
		//global $language;
		$PIO = PMCLibrary::getPIOInstance();
		
		if (FLAG_MODE == 1 && strstr($post['email'], 'flag')) return;
		if (FLAG_MODE == 2 && !strstr($post['email'], 'flag')) return;
		
		$reader = new Reader('module/geoip/GeoLite2-Country.mmdb');
		
		$iphost = strtolower($post['host']);
		
		
		try {
			$record = $reader->country(gethostbyname($post['host']));
		} catch (Exception $e) {
			$record = "";  // default value
		}
		
		if(ip2long($iphost)!==false) {
			
			if ($record != ""){
				$CountryID = $record->country->isoCode;
				$CountryName = $record->country->name;
			}
						
			if ($CountryName != "" || $record != ""){
				$arrLabels['{$NAME}'] .= ' <img src="module/geoip/flag/'.strtolower($CountryID).'.png" title="'.$CountryName.'" style="vertical-align: middle;margin-top: -2px;" alt="'.$CountryID.'">';
			} else {
				$arrLabels['{$NAME}'] .= ' <img src="module/geoip/flag/xx.png" title="Unknown" style="vertical-align: middle;margin-top: -2px;" alt="XX">';
			}
		} else { // host
			$parthost=''; $iscctld = false; $isgtld = false;

			if(preg_match('/([\w\-]+)\.([\w\-]+)$/',$iphost,$parts)) {

				// hinet/teksavvy/qwest/mchsi/smartone-vodafone/rr/swbell/sbcglobal/acanac/ameritech/
				// telus/charter/embarqhsd/comcast/verizon/sparqnet/taiwanmobile/userdns/pacbell/
				// comcastbusiness/fetnet/cgocable/cox/on/psu/thecloud/suddenlink/telstraclear/
				// liniacom/elisa-laajakaista/zsttk/bezeqint/arcor-ip/prtc/linearg/insightbb/george24/
				// pipex/amis/eircom/lijbrandt/ou/wlms-broadband/as9105/novuscom/btcentralplus/mnsi/
				// asretelecom/cgocable/spcsdns/indiana.edu/metrocast/twtelecom/frontiernet/onecommunications/
				// dslextreme/slicehost/as29550/clearwire-wmx/restechservices/net-infinity/myfairpoint.net/kymp.net/
				// gmavt.net/cia.com/sonic.net/newwavecomm.net/telia.com IP hack
				if($parts[1] == 'hinet' || $parts[1] == 'teksavvy' || $parts[1] == 'qwest'
				 || $parts[1] == 'mchsi' || $parts[1] == 'smartone-vodafone' || $parts[1] == 'rr'
				 || $parts[1] == 'swbell' || $parts[1] == 'sbcglobal' || $parts[1] == 'acanac'
				 || $parts[1] == 'ameritech' || $parts[1] == 'telus' || $parts[1] == 'charter'
				 || $parts[1] == 'embarqhsd' || $parts[1] == 'comcast' || $parts[1] == 'verizon'
				 || $parts[1] == 'sparqnet' || $parts[1] == 'taiwanmobile' || $parts[1] == 'userdns'
				 || $parts[1] == 'pacbell' || $parts[1] == 'comcastbusiness' || $parts[1] == 'fetnet'
				 || $parts[1] == 'cgocable' || $parts[1] == 'cox' || $parts[1] == 'on' || $parts[1] == 'psu'
				 || $parts[1] == 'thecloud' || $parts[1] == 'suddenlink' || $parts[1] == 'telstraclear'
				 || $parts[1] == 'liniacom' || $parts[1] == 'elisa-laajakaista' || $parts[1] == 'zsttk'
				 || $parts[1] == 'bezeqint' || $parts[1] == 'arcor-ip' || $parts[1] == 'prtc'
				 || $parts[1] == 'linearg' || $parts[1] == 'insightbb' || $parts[1] == 'george24'
				 || $parts[1] == 'pipex' || $parts[1] == 'amis' || $parts[1] == 'eircom' || $parts[1] == 'lijbrandt'
				 || $parts[1] == 'ou' || $parts[1] == 'wlms-broadband' || $parts[1] == 'as9105' || $parts[1] == 'novuscom'
				 || $parts[1] == 'btcentralplus' || $parts[1] == 'mnsi' || $parts[1] == 'asretelecom' || $parts[1] == 'cgocable'
				 || $parts[1] == 'spcsdns' || $parts[1] == 'indiana' || $parts[1] == 'metrocast' || $parts[1] == 'twtelecom'
				 || $parts[1] == 'frontiernet' || $parts[1] == 'onecommunications' || $parts[1] == 'dslextreme' || $parts[1] == 'slicehost'
				 || $parts[1] == 'as29550' || $parts[1] == 'clearwire-wmx' || $parts[1] == 'restechservices' || $parts[1] == 'net-infinity'
				 || $parts[1] == 'myfairpoint' || $parts[1] == 'kymp' || $parts[1] == 'gmavt' || $parts[1] == 'cia' || $parts[1] == 'sonic'
				 || $parts[1] == 'newwavecomm' || $parts[1] == 'telia') {
				 	if($parts[1] == 'hinet') preg_match('/([\w\-]+)\.([\w\-]+)\.(\w+)$/',$iphost,$parts); // show IP type for hinet
					if(preg_match('/^[a-z\-]*(\d+\-\d+)/',$iphost,$ipparts))
						$parthost = $ipparts[1].'-*.'.$parts[0];
					else
						$parthost = '*.'.$parts[0];
				// netvigator/bbtec/HKBN IP hack
				} elseif($parts[1] == 'netvigator' || $parts[1] == 'bbtec' || $parts[1] == 'ctinets') {
					if(preg_match('/^[a-z]*(\d{3})(\d{3})\d{3}\d{3}/',$iphost,$ipparts))
						$parthost = intval($ipparts[1]).'.'.intval($ipparts[2]).'.*.'.$parts[0];
					elseif($parts[1] == 'netvigator') {
						if (preg_match('/^(pcd\d{3})\d{3}/',$iphost,$ipparts)) // no IP hack for pcd******.netvigator.com
							$parthost = $ipparts[1].'*.'.$parts[0];
						elseif(preg_match('/^[a-z]*(\d{3})(\d{3})/',$iphost,$ipparts)) { // PCCW egg pain (n1164818021)
							if(intval($ipparts[2]) > 255) $ipparts[2] = substr($ipparts[2],0,-1);
							$parthost = $ipparts[1].'-'.$ipparts[2].'-*.'.$parts[0];
						} elseif(preg_match('/^\d+-\d+-(\d+)-(\d+)/',$iphost,$ipparts)) { // PCCW egg pain II (109-1-246-220.static.netvigator.com)
							$parthost = $ipparts[2].'-'.$ipparts[1].'-*.'.$parts[0];
						} elseif(preg_match('/^\d+\.\d+\.(\d+)\.(\d+)/',$iphost,$ipparts)) { // PCCW egg pain III (031.126.198.203.static.netvigator.com)
							$parthost = intval($ipparts[2]).'-'.intval($ipparts[1]).'-*.'.$parts[0];
						} else
							$parthost = '*.'.$parts[0];
					} else
						$parthost = '*.'.$parts[0];
				// pldt/quadranet/totbb/plus/ono/edpnet/telnor.net/dvois.com IP hack
				} elseif($parts[1] == 'pldt' || $parts[1] == 'quadranet' || $parts[1] == 'totbb' || $parts[1] == 'plus'
				 || $parts[1] == 'ono' || $parts[1] == 'edpnet' || $parts[1] == 'telnor' || $parts[1] == 'dvois') {
					if(preg_match('/^(\d+\.\d+)/',$iphost,$ipparts))
						$parthost = $ipparts[1].'.*.'.$parts[0];
					else
						$parthost = '*.'.$parts[0];
				} elseif($parts[1] == 'bjtelecom' || $parts[1] == 'cndata' || $parts[1] == 'ttn' || $parts[1] == 'siwnet' || $parts[1] == 'gibconnect') { // bjtelecom/cndata/ttn.net/siwnet.net/gibconnect.com IP hack
					if(preg_match('/^(\d+)\.(\d+)\.(\d+)\.(\d+)/',$iphost,$ipparts))
						$parthost = $ipparts[4].'-'.$ipparts[3].'-*.'.$parts[0];
					else
						$parthost = '*.'.$parts[0];
				} elseif($parts[1] == 'proxad') { // proxad IP hack
					if(preg_match('/^[a-z\-]*\d+\-\d+\-(\d+\-\d+)\-\d+\-\d+/',$iphost,$ipparts))
						$parthost = $ipparts[1].'-*.'.$parts[0];
					else
						$parthost = '*.'.$parts[0];
				} elseif($parts[1] == 'comunitel' || $parts[1] == 'ukrtel' || $parts[1] == 'vtr' || $parts[1] == 'on-nets') { // comunitel/ukrtel/vtr/on-nets IP hack
					if(preg_match('/^[\w\-]*(\d+)\-(\d+)-(\d+)\-(\d+)/',$iphost,$ipparts))
						$parthost = $ipparts[4].'-'.$ipparts[3].'-*.'.$parts[0];
					else
						$parthost = '*.'.$parts[0];
				} elseif($parts[1] == 'gaoland') { // gaoland IP hack
					if(preg_match('/^(\d+)\.(\d+)\.(\d+)\-(\d+)/',$iphost,$ipparts))
						$parthost = $ipparts[4].'-'.$ipparts[3].'-*.'.$parts[0];
					else
						$parthost = '*.'.$parts[0];
				} elseif($parts[1] == 'ctm') { // ctm.net IP hack (bb* only)
					if(preg_match('/^[\w]+\.bb(\d{3})(\d+)\./',$iphost,$ipparts))
						$parthost = intval($ipparts[1]).'-'.intval($ipparts[2]).'-*.'.$parts[0];
					else
						$parthost = '*.'.$parts[0];
				// web-pass/windstream/1dial/mundo-r/g3telecom IP hack
				} elseif($parts[1] == 'web-pass' || $parts[1] == 'windstream' || $parts[1] == '1dial' || $parts[1] == 'mundo-r' || $parts[1] == 'g3telecom') {
					if(preg_match('/^\w+\.\d+\.(\d+)\.(\d+)/',$iphost,$ipparts))
						$parthost = $ipparts[2].'-'.$ipparts[1].'-*.'.$parts[0];
					else
						$parthost = '*.'.$parts[0];
				} elseif($parts[1] == 'sky' || $parts[1] == 'aol') { // sky.com/aol.com IP hack
					if(preg_match('/^([0-9a-f]{2})([0-9a-f]{2})[0-9a-f]{2}[0-9a-f]{2}\./',$iphost,$ipparts))
						$parthost =  hexdec($ipparts[1]).'-'.hexdec($ipparts[2]).'-*.'.$parts[0];
					else
						$parthost = '*.'.$parts[0];
				} elseif($parts[1] == 't-dialin' || $parts[1] == 'perspektivbredband') { // t-dialin/perspektivbredband.net IP hack
					if(preg_match('/^[a-z]([0-9a-f]{2})([0-9a-f]{2})[0-9a-f]{2}[0-9a-f]{2}\./',$iphost,$ipparts))
						$parthost =  hexdec($ipparts[1]).'-'.hexdec($ipparts[2]).'-*.'.$parts[0];
					else
						$parthost = '*.'.$parts[0];
				} elseif($parts[1] == 'tmodns') { // tmodns IP hack
					if(preg_match('/^[a-z][0-9a-f]{2}[0-9a-f]{2}([0-9a-f]{2})([0-9a-f]{2})\./',$iphost,$ipparts))
						$parthost =  hexdec($ipparts[2]).'-'.hexdec($ipparts[1]).'-*.'.$parts[0];
					else
						$parthost = '*.'.$parts[0];
				} elseif($parts[1] == 'mediaways' || $parts[1] == 'optonline') { // mediaways/optonline IP hack
					if(preg_match('/^\w+\-([0-9a-f]{2})([0-9a-f]{2})[0-9a-f]{2}[0-9a-f]{2}\./',$iphost,$ipparts))
						$parthost =  hexdec($ipparts[1]).'-'.hexdec($ipparts[2]).'-*.'.$parts[0];
					else
						$parthost = '*.'.$parts[0];
				} elseif($parts[1] == 'rima-tde' || $parts[1] == 'myvzw') { // rima-tde/myvzw IP hack
					if(preg_match('/^\d+\.[a-z]+\-(\d+\-\d+)\-/',$iphost,$ipparts))
						$parthost =  $ipparts[1].'-*.'.$parts[0];
					else
						$parthost = '*.'.$parts[0];
				} elseif($parts[1] == 'theplanet') { // theplanet IP hack
					if(preg_match('/^[0-9a-f]+\.[0-9a-f]+\.([0-9a-f]{2})([0-9a-f]{2})/',$iphost,$ipparts)) {
						$ipdec = hexdec($ipparts[1]);
						$parthost = hexdec($ipparts[2]).'-'.hexdec($ipparts[1]).'-*.'.$parts[0];
					} else
						$parthost = '*.'.$parts[0];
				} elseif(preg_match('/on-nets$/',$parts[1])) { // on-nets IP hack
					if(preg_match('/(\d+)\-(\d+)-on-nets/',$parts[1],$ipparts))
						$parthost = $ipparts[2].'.'.$ipparts[1].'.*.on-nets.com';
					else
						$parthost = '*-on-nets.com';
				} else {
					$lastpart = $parts[2];
					$isgtld = $this->_isgTLD($lastpart);

					if(!$isgtld) {
						$cctld = array('ac','ad','ae','af','ag','ai','al','am','an','ao','aq','ar','as','at','au','aw','ax','az','ba','bb','bd','be','bf','bg','bh','bi','bj','bm','bn','bo','br','bs','bt','bw','by','bz','ca','cc','cd','cf','cg','ch','ci','ck','cl','cm','cn','co','cr','cu','cv','cx','cy','cz','de','dj','dk','dm','do','dz','ec','ee','eg','er','es','et','eu','fi','fj','fk','fm','fo','fr','ga','gd','ge','gf','gg','gh','gi','gl','gm','gn','gp','gq','gr','gs','gt','gu','gw','gy','hk','hm','hn','hr','ht','hu','id','ie','il','im','in','io','iq','ir','is','it','je','jm','jo','jp','ke','kg','kh','ki','km','kn','kp','kr','kw','ky','kz','la','lb','lc','li','lk','lr','ls','lt','lu','lv','ly','ma','mc','md','me','mg','mh','mk','ml','mm','mn','mo','mp','mq','mr','ms','mt','mu','mv','mw','mx','my','mz','na','nc','ne','nf','ng','ni','nl','no','np','nr','nu','nz','om','pa','pe','pf','pg','ph','pk','pl','pn','pr','ps','pt','pw','py','qa','re','ro','rs','ru','rw','sa','sb','sc','sd','se','sg','sh','si','sk','sl','sm','sn','sr','st','su','sv','sy','sz','tc','td','tf','tg','th','tj','tk','tl','tm','tn','to','tr','tt','tv','tw','tz','ua','ug','uk','us','uy','uz','va','vc','ve','vg','vi','vn','vu','wf','ws','ye','za','zm','zw');
						foreach($cctld as $tld) {
							if($lastpart == $tld) {
								$iscctld = true;
								preg_match('/([\w\-]+)\.([\w\-]+)\.(\w+)$/',$iphost,$parts);
								$isgtld = $this->_isgTLD($parts[2],array('ac','ad','co','ed','go','gr'.'lg','ne','or','ind','ltd','nic','plc','vet')); // '.co.uk' etc. are common
								if($isgtld) {
									// kbronet/seed/so-net.net.tw/tfn/giga/lsc/canvas/tpgi/adam/iinet/tbcnet/xtra/nkcatv/telesp/netvision/twt1/dodo/
									// adsl24/btvm/netspace/connections.net.nz/orcon.net.nz/kbtelecom.net.tw/tstt.net.tt/vivax.com.br/
									// clear.net.nz/hiway.net.tw/ihug.co.nz/asta-net.com.pl/eonet.ne.jp IP hack
									if($parts[1] == 'kbronet' || $parts[1] == 'seed' || $parts[1] == 'so-net'
									 || $parts[1] == 'tfn' || $parts[1] == 'giga' || $parts[1] == 'lsc'
									 || $parts[1] == 'canvas' || $parts[1] == 'tpgi' || $parts[1] == 'adam'
									 || $parts[1] == 'iinet' || $parts[1] == 'tbcnet' || $parts[1] == 'xtra'
									 || $parts[1] == 'nkcatv' || $parts[1] == 'telesp' || $parts[1] == 'netvision'
									 || $parts[1] == 'twt1' || $parts[1] == 'dodo' || $parts[1] == 'adsl24' || $parts[1] == 'btvm'
									 || $parts[1] == 'connections' || $parts[1] == 'orcon' || $parts[1] == 'kbtelecom' || $parts[1] == 'tstt'
									 || $parts[1] == 'vivax' || $parts[1] == 'clear' || $parts[1] == 'hiway' || $parts[1] == 'ihug' || $parts[1] == 'asta-net' || $parts[1] == 'eonet') {
										if(preg_match('/^(\d+\-\d+)/',$iphost,$ipparts))
											$parthost = $ipparts[0].'-*.'.$parts[0];
										elseif($parts[1] == 'seed' && preg_match('/^\w+\-(\d+\-\d+)-\d+/',$iphost,$ipparts)) // seed h* IP hack
											$parthost = $ipparts[1].'-*.'.$parts[0];
										else
											$parthost = '*.'.$parts[0];
									// i-cable/singnet/optusnet/plala/rosenet/bethere/asianet/home.ne.jp/hidatakayama/apol/pikara/bigpond/netspace.net.au/orange.co.il/
									// callplus/prod-infinitum/xnet/unwired/e-mobile/gol.ne.jp/ksu.edu.tw/monash.edu.au/sinica.edu.tw/cjcu.edu.tw/asianet/tic.net.tw/
									// dialog.net.pl/canet.ne.jp/catvisp.net.tw/connect.net.au/anteldata.net.uy/iburst.co.za/ccnw.ne.jp IP hack
									} elseif($parts[1] == 'hkcable' || $parts[1] == 'singnet' || $parts[1] == 'optusnet'
									 || $parts[1] == 'plala' || $parts[1] == 'rosenet' || $parts[1] == 'bethere'
									 || $parts[1] == 'asianet' || $parts[1] == 'home' || $parts[1] == 'hidatakayama'
									 || $parts[1] == 'apol' || $parts[1] == 'pikara' || $parts[1] == 'bigpond'
									 || $parts[1] == 'netspace' || $parts[1] == 'orange' || $parts[1] == 'callplus'
									 || $parts[1] == 'prod-infinitum' || $parts[1] == 'xnet' || $parts[1] == 'unwired'
									 || $parts[1] == 'e-mobile' || $parts[1] == 'gol' || $parts[1] == 'ksu' || $parts[1] == 'monash'
									 || $parts[1] == 'sinica' || $parts[1] == 'cjcu' || $parts[1] == 'asianet' || $parts[1] == 'tic'
									 || $parts[1] == 'dialog' || $parts[1] == 'canet' || $parts[1] == 'catvisp' || $parts[1] == 'connect'
									 || $parts[1] == 'anteldata' || $parts[1] == 'iburst' || $parts[1] == 'ccnw') {
										if(preg_match('/^[a-z\-]*(\d+\-\d+)-\d+\-\d+/',$iphost,$ipparts))
											$parthost = $ipparts[1].'-*.'.$parts[0];
										else
											$parthost = '*.'.$parts[0];
									} elseif($parts[1] == 'ocn' || $parts[1] == 'nttpc') { // OCN/nttpc hack (no IP hack available)
											preg_match('/([\w\-]+\.){3}(\w+)$/',$iphost,$parts);
											$parthost = '*.'.$parts[0];
									} elseif($parts[1] == 'infoweb') { // infoweb hack (no IP hack available)
											preg_match('/([\w\-]+\.){6}(\w+)$/',$iphost,$parts);
											$parthost = '*.'.$parts[0];
									// tcol/yournet/m1connect/exetel/megaegg/pacific/snap.net.nz IP hack
									} elseif($parts[1] == 'tcol' || $parts[1] == 'yournet' || $parts[1] == 'm1connect' || $parts[1] == 'exetel'
									 || $parts[1] == 'megaegg' || $parts[1] == 'pacific' || $parts[1] == 'snap') {
										if(preg_match('/^(\d+)\.(\d+)\.(\d+)\.(\d+)/',$iphost,$ipparts))
											$parthost = $ipparts[4].'-'.$ipparts[3].'-*.'.$parts[0];
										else
											$parthost = '*.'.$parts[0];
									} elseif($parts[1] == 'eaccess' || $parts[1] == 'gvt' || $parts[1] == 'aanet') { // eaccess/gvt/aanet IP hack
										if(preg_match('/^(\d+)\.(\d+)/',$iphost,$ipparts))
											$parthost = $ipparts[0].'.*.'.$parts[0];
										else
											$parthost = '*.'.$parts[0];
									} elseif($parts[1] == 'tinp' || $parts[1] == 'savecom' || $parts[1] == 'cdbnet') { // tinp/savecom/cdbnet IP hack
										if(preg_match('/^(\d+)\-(\d+)-(\d+)\-(\d+)/',$iphost,$ipparts))
											$parthost = $ipparts[4].'-'.$ipparts[3].'-*.'.$parts[0];
										else
											$parthost = '*.'.$parts[0];
									} elseif($parts[1] == 'ucom' || $parts[1] == 'gyao') { // ucom/gyao IP hack
										if(preg_match('/^(\d+)x(\d+)x(\d+)x(\d+)/',$iphost,$ipparts))
											$parthost = $ipparts[1].'-'.$ipparts[2].'-*.'.$parts[0];
										else
											$parthost = '*.'.$parts[0];
									} elseif($parts[1] == 'totalbb') { // totalbb IP hack
										if(preg_match('/^[\w\-]+\.(\d+)-(\d+)\-(\d+)/',$iphost,$ipparts))
											$parthost = $ipparts[3].'-'.$ipparts[2].'-*.'.$parts[0];
										else
											$parthost = '*.'.$parts[0];
									} elseif($parts[1] == 'sig') { // sig.com.tw IP hack
										if(preg_match('/^[\w\-]+\.(\d+)\.(\d+)/',$iphost,$ipparts))
											$parthost = $ipparts[2].'-'.$ipparts[1].'-*.'.$parts[0];
										else
											$parthost = '*.'.$parts[0];
									} elseif($parts[1] == 'maxonline') { // maxonline.com.sg IP hack (proxy.* only)
										if(preg_match('/^proxy\.(\d+)\.(\d+)\./',$iphost,$ipparts))
											$parthost = $ipparts[1].'-'.$ipparts[2].'-*.'.$parts[0];
										else
											$parthost = '*.'.$parts[0];
									} elseif($parts[1] == 'wakwak' || $parts[1] == 'ntti') { // wakwak/ntti.net.sg IP hack
										if(preg_match('/^[\w\-]+\.(\d+)-(\d+)\-(\d+)/',$iphost,$ipparts))
											$parthost = $ipparts[1].'-'.$ipparts[2].'-*.'.$parts[0];
										else
											$parthost = '*.'.$parts[0];
									} elseif($parts[1] == 'gcn') { // gcn IP hack
										if(preg_match('/^\w+\.(\d{3})(\d{2})\d+/',$iphost,$ipparts))
											$parthost = $ipparts[1].'-'.$ipparts[2].'-*.'.$parts[0];
										else
											$parthost = '*.'.$parts[0];
									} elseif($parts[1] == 'tnc' || $parts[1] == 'thn' || $parts[1] == 'tokai') { // tnc/thn/tokai IP hack
										if(preg_match('/^[\w\-]+\.[a-z]+(\d{3})(\d{3})\d{3}/',$iphost,$ipparts))
											$parthost = intval($ipparts[1]).'-'.intval($ipparts[2]).'-*.'.$parts[0];
										else
											$parthost = '*.'.$parts[0];
									} elseif($parts[1] == 'ctt') { // ctt IP hack
										if(preg_match('/^[\w\-]+\.[a-z]+\d{3}(\d{3})(\d{3})/',$iphost,$ipparts))
											$parthost = intval($ipparts[2]).'-'.intval($ipparts[1]).'-*.'.$parts[0];
										else
											$parthost = '*.'.$parts[0];
									} elseif($parts[1] == 'ayu') { // ayu IP hack
										if(preg_match('/^[\w\-]+\-[a-z]+(\d{3})(\d{3})\d{3}/',$iphost,$ipparts))
											$parthost = intval($ipparts[1]).'-'.intval($ipparts[2]).'-*.'.$parts[0];
										else
											$parthost = '*.'.$parts[0];
									} elseif($parts[1] == 'dongfong') { // dongfong IP hack
										if(preg_match('/^(\d+)\.(\d+)-(\d+)\-[a-z]+(\d+)/',$iphost,$ipparts))
											$parthost = $ipparts[1].'-'.$ipparts[2].'-*.'.$parts[0];
										else
											$parthost = '*.'.$parts[0];
									} elseif($parts[1] == 'mesh') { // mesh.ad.jp IP hack (partly)
										if(preg_match('/^\w+\-(\d+\-\d+)-\d+\-\d+/',$iphost,$ipparts))
											$parthost = $ipparts[1].'-*.'.$parts[0];
										else
											$parthost = '*.'.$parts[0];
									} elseif($parts[1] == 'deloitte') { // deloitte IP hack
										if(preg_match('/^\w+\-(\d+\-\d+)-\d+/',$iphost,$ipparts))
											$parthost = $ipparts[1].'-*.'.$parts[0];
										else
											$parthost = '*.'.$parts[0];
									} elseif($parts[1] == 'iprimus') { // iprimus IP hack
										if(preg_match('/^\d+\.\d+\-(\d+)\-(\d+)/',$iphost,$ipparts))
											$parthost = $ipparts[2].'-'.$ipparts[1].'-*.'.$parts[0];
										else
											$parthost = '*.'.$parts[0];
									} elseif($parts[1] == 'tm') { // tm.net.my IP hack ( *-home, *-hsbb only)
										if(preg_match('/^\d+\.\d+\.(\d+)\.(\d+)\.\w+\-(home|hsbb)/',$iphost,$ipparts))
											$parthost = $ipparts[2].'-'.$ipparts[1].'-*.'.$parts[0];
										elseif(preg_match('/(\d+)\.(\d+)\.in-addr/',$iphost,$ipparts)) // in-addr hack
											$parthost = $ipparts[2].'-'.$ipparts[1].'-*.'.$parts[0];
										else
											$parthost = '*.'.$parts[0];
									} elseif($parts[1] == '163data' || $parts[1] == 'cta') { // 163data/cta IP hack
										if(preg_match('/^\d+\.\d+\.(\d+)\.(\d+)\./',$iphost,$ipparts))
											$parthost = $ipparts[2].'-'.$ipparts[1].'-*.'.$parts[0];
										else
											$parthost = '*.'.$parts[0];
									} elseif($parts[1] == 'zaq') { // zaq IP hack
										if(preg_match('/^zaq([0-9a-f]{2})([0-9a-f]{2})[0-9a-f]{2}[0-9a-f]{2}\./',$iphost,$ipparts))
											$parthost = hexdec($ipparts[1]).'-'.hexdec($ipparts[2]).'-*.'.$parts[0];
										else
											$parthost = '*.'.$parts[0];
									// dion/kcn-tv/janis/panda-world/coralnet IP hack
									} elseif($parts[1] == 'dion' || $parts[1] == 'kcn-tv' || $parts[1] == 'janis' || $parts[1] == 'panda-world' || $parts[1] == 'coralnet') {
										if(preg_match('/^[a-z]*(\d{3})(\d{3})/',$iphost,$ipparts))
											$parthost = intval($ipparts[1]).'.'.intval($ipparts[2]).'.*.'.$parts[0];
										else
											$parthost = '*.'.$parts[0];
									} elseif(preg_match('/tinp$/',$parts[1])) { // tinp IP hack 2
										if(preg_match('/^(\d+)\-(\d+)-(\d+)\-(\d+)/',$iphost,$ipparts))
											$parthost = $ipparts[4].'-'.$ipparts[3].'-*.tinp.com.tw';
										else
											$parthost = '*.'.$parts[0];
									} else {
										$parthost = '*.'.$parts[0];
									}
								} else {
									if($parts[2] == 'wanadoo' && $parts[3] == 'fr') { // wanadoo.fr IP hack
										if(preg_match('/^[\w\-]+\.[a-z]{1}(\d+-\d+)/',$iphost,$ipparts))
											$parthost = $ipparts[1].'-*.'.$parts[2].'.'.$parts[3];
										else
											$parthost = '*.'.$parts[2].'.'.$parts[3];
									// corbina/j-cnet/numericable/telekom.at/tele2.se/tele2.at/kou/bbiq/inode/superkabel/novis.pt/maconet.cz/quicknet.nl/pbthawe.eu IP hack
									} elseif($parts[2] == 'corbina' || $parts[2] == 'j-cnet' || $parts[2] == 'numericable'
									|| $parts[2] == 'telekom' || $parts[2] == 'tele2' || $parts[2] == 'kou' || $parts[2] == 'bbiq'
									|| $parts[2] == 'inode' || $parts[2] == 'superkabel' || $parts[2] == 'novis' || $parts[2] == 'maconet' || $parts[2] == 'quicknet' || $parts[2] == 'pbthawe') {
										if(preg_match('/^[a-z]?(\d+\-\d+)/',$iphost,$ipparts))
											$parthost = $ipparts[1].'-*.'.$parts[2].'.'.$parts[3];
										else
											$parthost = '*.'.$parts[2].'.'.$parts[3];
									// commufa/unitymediagroup/yaroslavl/otenet/scarlet/netcabo/mtu-net/eunet/chello.nl/chello.pl/net-htp/upc/t3/telfort/qsc/comhem/
									// mnet-online/upcbroadband/scarlet.be/ownit/itscom/sk.ca/swipnet.se/nextra.sk/nationalcablenetworks.ru/netcologne.de IP hack
									} elseif($parts[2] == 'commufa' || $parts[2] == 'unitymediagroup' || $parts[2] == 'yaroslavl'
									|| $parts[2] == 'otenet' || $parts[2] == 'scarlet' || $parts[2] == 'netcabo' || $parts[2] == 'mtu-net'
									|| $parts[2] == 'eunet' || $parts[2] == 'chello' || $parts[2] == 'net-htp' || $parts[2] == 'upc'
									|| $parts[2] == 't3' || $parts[2] == 'telfort' || $parts[2] == 'qsc' || $parts[2] == 'comhem'
									|| $parts[2] == 'mnet-online' || $parts[2] == 'upcbroadband' || $parts[2] == 'scarlet' || $parts[2] == 'ownit'
									|| $parts[2] == 'itscom' || $parts[2] == 'sk' || $parts[2] == 'swipnet' || $parts[2] == 'nextra'
									|| $parts[2] == 'nationalcablenetworks' || $parts[2] == 'netcologne') {
										if(preg_match('/^[a-z]*-?(\d+\-\d+)/',$iphost,$ipparts))
											$parthost = $ipparts[1].'-*.'.$parts[2].'.'.$parts[3];
										elseif($parts[2] == 'chello' && $parts[3] == 'pl') { // chello.pl IP hack
											if(preg_match('/^[a-z]*(\d{3})(\d{3})\d{3}\d{3}/',$iphost,$ipparts))
												$parthost = intval($ipparts[1]).'-'.intval($ipparts[2]).'-*.'.$parts[2].'.'.$parts[3];
											else
												$parthost = '*.'.$parts[2].'.'.$parts[3];
										} else
											$parthost = '*.'.$parts[2].'.'.$parts[3];
									} elseif($parts[2] == 'bbexcite' || $parts[2] == 'estpak' || $parts[2] == 'sx' || $parts[2] == 'cdi') { // bbexcite/estpak/sx.cn/cdi.no IP hack
										if(preg_match('/^(\d+)\.(\d+)\.(\d+)\.(\d+)/',$iphost,$ipparts))
											$parthost = $ipparts[4].'-'.$ipparts[3].'-*.'.$parts[2].'.'.$parts[3];
										else
											$parthost = '*.'.$parts[2].'.'.$parts[3];
									} elseif($parts[2] == 'zoot') { // zoot IP hack
										if(preg_match('/^(\d+)\.(\d+)\.(\d+)\.(\d+)/',$iphost,$ipparts))
											$parthost = $ipparts[1].'-'.$ipparts[2].'-*.'.$parts[2].'.'.$parts[3];
										else
											$parthost = '*.'.$parts[2].'.'.$parts[3];
									} elseif($parts[2] == 'bell') { // bell.ca IP hack
										if(preg_match('/^\w+\-\w+\-(\d+)\./',$iphost,$ipparts)) {
											$ipparts = explode('.',long2ip($ipparts[1]));
											$parthost = $ipparts[0].'-'.$ipparts[1].'-*.'.$parts[2].'.'.$parts[3];
										} else
											$parthost = '*.'.$parts[2].'.'.$parts[3];
									} elseif($parts[2] == 'tiscali') { // tiscali.it IP hack
										if(preg_match('/^\w+\-\w+\-(\d+\-\d+)\-/',$iphost,$ipparts)) {
											$parthost = $ipparts[1].'-*.'.$parts[2].'.'.$parts[3];
										} else
											$parthost = '*.'.$parts[2].'.'.$parts[3];
									} elseif($parts[2] == 'dbnet') { // dbnet.dk IP hack
										if(preg_match('/^(\d+)\./',$iphost,$ipparts)) {
											$ipparts = explode('.',long2ip($ipparts[1]));
											$parthost = $ipparts[0].'-'.$ipparts[1].'-*.'.$parts[2].'.'.$parts[3];
										} else
											$parthost = '*.'.$parts[2].'.'.$parts[3];
									} elseif($parts[2] == 'mm') { // mm.pl IP hack
										if(preg_match('/^[\w]+-(\d+\-\d+)\-/',$iphost,$ipparts)) {
											$parthost = $ipparts[1].'-*.'.$parts[0]; //include subdomain
										} else
											$parthost = '*.'.$parts[2].'.'.$parts[3];
									} elseif($parts[2] == 'club-internet') { // club-internet IP hack
										if(preg_match('/^\w+\-\d+\-(\d+)\-(\d+)\-(\d+)\-(\d+)/',$iphost,$ipparts))
											$parthost = $ipparts[1].'-'.$ipparts[2].'-*.'.$parts[2].'.'.$parts[3];
										else
											$parthost = '*.'.$parts[2].'.'.$parts[3];
									} elseif($parts[2] == 'kabel-badenwuerttemberg') { // kabel-badenwuerttemberg IP hack
										if(preg_match('/^\w+\-\w+\-(\d+\-\d+)\-\d+\-\d+/',$iphost,$ipparts))
											$parthost = $ipparts[1].'-*.'.$parts[2].'.'.$parts[3];
										else
											$parthost = '*.'.$parts[2].'.'.$parts[3];
									} elseif($parts[2] == 'telecomitalia') { // telecomitalia IP hack
										if(preg_match('/^[\w\-]+\.(\d+)\-(\d+)\-/',$iphost,$ipparts))
											$parthost = $ipparts[2].'-'.$ipparts[1].'-*.'.$parts[2].'.'.$parts[3];
										else
											$parthost = '*.'.$parts[2].'.'.$parts[3];
									} elseif($parts[2] == 'belgacom' || $parts[2] == 'videotron') { // belgacom/videotron IP hack
										if(preg_match('/^[a-z]*\d+\.\d+\-(\d+)\-(\d+)\./',$iphost,$ipparts))
											$parthost = $ipparts[2].'-'.$ipparts[1].'-*.'.$parts[2].'.'.$parts[3];
										else
											$parthost = '*.'.$parts[2].'.'.$parts[3];
									} elseif($parts[2] == 'orange') { // orange.es IP hack
										if(preg_match('/^\d+\.[a-z]+(\d+)\-(\d+)\-/',$iphost,$ipparts))
											$parthost = $ipparts[1].'-'.$ipparts[2].'-*.'.$parts[2].'.'.$parts[3];
										else
											$parthost = '*.'.$parts[2].'.'.$parts[3];
									} elseif($parts[2] == 'libero' || $parts[2] == 'net24') { // libero.it/net24.it IP hack
										if(preg_match('/^[\w\-]+\.(\d+)\-(\d+)\./',$iphost,$ipparts))
											$parthost = $ipparts[2].'-'.$ipparts[1].'-*.'.$parts[2].'.'.$parts[3];
										else
											$parthost = '*.'.$parts[2].'.'.$parts[3];
									} elseif($parts[2] == 'sh') { // sh.cn IP hack
										if(preg_match('/^\w+\-\d+\-(\d+)\-(\d+)/',$iphost,$ipparts))
											$parthost = $ipparts[2].'-'.$ipparts[1].'-*.'.$parts[2].'.'.$parts[3];
										else
											$parthost = '*.'.$parts[2].'.'.$parts[3];
									} elseif($parts[2] == 'su29' || $parts[2] == 'getinternet') { // su29.ru/getinternet.no IP hack
										if(preg_match('/^[a-z]+\-(\d+)\.(\d+)\./',$iphost,$ipparts))
											$parthost = $ipparts[1].'-'.$ipparts[2].'-*.'.$parts[2].'.'.$parts[3];
										else
											$parthost = '*.'.$parts[2].'.'.$parts[3];
									} elseif($parts[2] == 'wcgwave' ||$parts[2] == 'hol') { // wcgwave/hol.gr IP hack
										if(preg_match('/^[a-z]+(\d{3})(\d{3})\d{3}\d{3}/',$iphost,$ipparts))
											$parthost = intval($ipparts[1]).'-'.intval($ipparts[2]).'-*.'.$parts[2].'.'.$parts[3];
										else
											$parthost = '*.'.$parts[2].'.'.$parts[3];
									} elseif($parts[2] == 'bredbandsbolaget') { // bredbandsbolaget IP hack
										if(preg_match('/^\w+\-[0-9a-f]{2}[0-9a-f]{2}([0-9a-f]{2})([0-9a-f]{2})\./',$iphost,$ipparts))
											$parthost = hexdec($ipparts[2]).'-'.hexdec($ipparts[1]).'-*.'.$parts[2].'.'.$parts[3];
										else
											$parthost = '*.'.$parts[2].'.'.$parts[3];
									} elseif($parts[2] == 'ucd') { // ucd.ie IP hack
										if(preg_match('/^\w+\-([0-9a-f]{2})([0-9a-f]{2})[0-9a-f]{2}[0-9a-f]{2}\./',$iphost,$ipparts))
											$parthost = hexdec($ipparts[1]).'-'.hexdec($ipparts[2]).'-*.'.$parts[2].'.'.$parts[3];
										else
											$parthost = '*.'.$parts[2].'.'.$parts[3];
									} elseif($parts[2] == 'cybercity' || $parts[2] == 'ziggozakelijk' || $parts[2] == 'enternet') { // cybercity.dk/ziggozakelijk.nl/enternet.hu IP hack
										if(preg_match('/^(0x)?([0-9a-f]{2})([0-9a-f]{2})[0-9a-f]{2}[0-9a-f]{2}\./',$iphost,$ipparts))
											$parthost = hexdec($ipparts[2]).'-'.hexdec($ipparts[3]).'-*.'.$parts[2].'.'.$parts[3];
										else
											$parthost = '*.'.$parts[2].'.'.$parts[3];
									} elseif($parts[2] == 'mdcc-fun') { // mdcc-fun.de IP hack
										if(preg_match('/^\w+\-\w+\-(\d+-\d+)-\d+-\d+/',$iphost,$ipparts))
											$parthost = $ipparts[1].'-*.'.$parts[2].'.'.$parts[3];
										else
											$parthost = '*.'.$parts[2].'.'.$parts[3];
									// gavle/t-ipconnect/telenet/direct-adsl/wanadoo.nl/catch.no IP hack
									} elseif($parts[2] == 'gavle' || $parts[2] == 't-ipconnect' || $parts[2] == 'telenet' || $parts[2] == 'direct-adsl'
									 || $parts[2] == 'wanadoo' || $parts[2] == 'catch') {
										if(preg_match('/^[a-z]([0-9a-f]{2})([0-9a-f]{2})[0-9a-f]{2}[0-9a-f]{2}\./',$iphost,$ipparts))
											$parthost = hexdec($ipparts[1]).'-'.hexdec($ipparts[2]).'-*.'.$parts[2].'.'.$parts[3];
										elseif($parts[2] == 'telenet' && preg_match('/^(\d+\-\d+)/',$iphost,$ipparts))
												$parthost = $ipparts[1].'-*.'.$parts[2].'.'.$parts[3];
										else
											$parthost = '*.'.$parts[2].'.'.$parts[3];
									} else {
										$parthost = '*.'.$parts[2].'.'.$parts[3];
									}
								}
								break;
							}
						}
					} else {
						$parthost = '*.'.$parts[0];
					}
					if(!$iscctld && !$isgtld) {
						if($parts[1] == 'in-addr' && $parts[2] == 'arpa') { // in-addr.apra hack
							if(preg_match('/(\d+)\.(\d+)\.in-addr/',$iphost,$ipparts))
								$parthost = $ipparts[2].'-'.$ipparts[1].'-*.'.$parts[1].'.'.$parts[2];
							else
								$parthost = '*.'.$parts[1].'.'.$parts[2];
						} elseif($parts[1] == 'ha' && $parts[2] == 'cnc') { // ha.cnc IP hack
							if(preg_match('/^(\d+)\.(\d+)\.(\d+)\.(\d+)/',$iphost,$ipparts))
								$parthost = $ipparts[4].'-'.$ipparts[3].'-*.'.$parts[1].'.'.$parts[2];
							else
								$parthost = '*.'.$parts[1].'.'.$parts[2];
						} elseif(preg_match('/-bj-cnc$/',$parts[2])) { // *-bj-cnc IP hack
							if(preg_match('/^(\d+)\.(\d+)\.(\d+)\.(\d+)/',$iphost,$ipparts))
								$parthost = $ipparts[1].'-'.$ipparts[2].'-*.BJ-CNC';
							else
								$parthost = '*.'.$parts[2];
						} else
							$parthost = $iphost; // unresolvable
					}
				}
			} else {
				$parthost = $iphost; // unresolvable
			}

			if ($record != ""){
				$CountryID = $record->country->isoCode;
				$CountryName = $record->country->name;
			}
						
			if ($CountryName != "" || $record != ""){
				$arrLabels['{$NAME}'] .= ' <img src="module/geoip/flag/'.strtolower($CountryID).'.png" title="'.$CountryName.'" style="vertical-align: middle;margin-top: -2px;" alt="'.$CountryID.'">';
			} else {
				$arrLabels['{$NAME}'] .= ' <img src="module/geoip/flag/xx.png" title="Unknown" style="vertical-align: middle;margin-top: -2px;" alt="XX">';
			}
			}
	}

	public function autoHookThreadReply(&$arrLabels, $post, $isReply){
		$this->autoHookThreadPost($arrLabels, $post, $isReply);
	}
	
} 
