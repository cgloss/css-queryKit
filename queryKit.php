<?php
// set a salt
$key = 'wXr375';
// check if temps is set from the ajax call
if(isset($_REQUEST['qk_temps_'.$key])){
	foreach (explode(',',$_REQUEST['qk_temps_'.$key]) as $temp){
		unlink((string)$temp);
	}
}elseif(!class_exists('querykit')){
	class querykit{
		// inits
		static $path,$salt;
	    private $dom,$temps,$times,$till,$refresh;
	    public $term,$html,$now,$zone;
	    
	    public function __construct($arg,$salt,$zone,$refresh='header'){
			// set refresh type
			$this->refresh = $refresh;
			// set timezone
			$this->zone = $zone;
			// set temp store
			$this->temps = array();
			// set current dt
			$this->now = new DateTime();
			// self path
			self::$path = $_SERVER['PHP_SELF'];
			// bake dom
			$this->dom = new DOMDocument();
			// at least provide some semblance of a handshake
			self::$salt = $salt;
			// set term
			$this->term = $arg;
			// dom parse self
			$this->html = $this->qk_parse(file_get_contents('..'.self::$path, FILE_USE_INCLUDE_PATH));
			// set the seconds till next query rule
			$this->till = $this->gettill($this->times);
			die();
	    }
	    protected function qk_parse($html){
			$this->dom->loadHTML($html);
			// make array of temp css files, for later removal, and swap the link path to the temps
			foreach($this->dom->getElementsByTagName("link") as $l) {
			    if($l->getAttribute("rel") == "stylesheet") {
			        $temp = $this->qk_build($l->getAttribute("href"));
			        $this->temps[]=realpath($temp);
			        $l->setAttribute("href",$temp);
			    }
			}
			// define the script to delete the temp css' and subsequently itself
			$script = 'window.addEventListener("load", qk_del);
			function qk_del(){ 
				var request = new XMLHttpRequest();
				request.open("POST", "'.self::$path.'", true);
				request.setRequestHeader("Content-Type", "application/x-www-form-urlencoded; charset=UTF-8");
				request.send("qk_temps_'.self::$salt.'='.implode(',',$this->temps).'");
				var qkthis = document.getElementById("qk_temp");
				qkthis.parentNode.removeChild(qkthis);
			}';
			// add the script
			$dscript = $this->dom->createElement('script', $script);
			$dscript->setAttribute("id",'qk_temp');
			// append the script above the close body
			$this->dom->getElementsByTagName('body')->item(0)->appendChild($dscript); 
			return $this->dom->saveHTML();

		}
		protected function qk_build($cssfile){
			$path_parts = pathinfo($cssfile);
			$temp = tempnam($path_parts['dirname'], $path_parts['filename']);
			$data = file_get_contents($cssfile, FILE_USE_INCLUDE_PATH);
			file_put_contents($temp.'.css', $data);
			$temp_parts = pathinfo($temp);
			unlink($temp);
			$css = $path_parts['dirname'].'/'.$temp_parts['filename'].'.css';
			$this->qk_proctemp($css);
			return $css;
		}
		protected function qk_proctemp($cssfile){
			// get file content
			$css=file_get_contents((string)$cssfile);
			// set the regex
			$pattern = '/@'.$this->term.'[^{]+\{([\s\S]+?})\s*}/im';
			// get matches
			preg_match_all($pattern, $css, $ins);
			// loop each term match
			foreach ($ins[0] as $k => $v){
				$start = $this->getset('start',$v);
				$end = $this->getset('end',$v);
				$this->now->setTimezone(new DateTimeZone($start->getTimezone()->getName()));

				// check if css should be applied according to arguments set in css file
				if ($this->now >= $start && $this->now <= $end) {
				    $css=str_replace($v, $ins[1][$k], $css);
				}	
			}
			// overwrite temp css file with applicable rules
			file_put_contents((string)$cssfile, $css);
		}
	    protected function getset($arg,$str){
			preg_match_all('/(s?'.$arg.'.*?)\.?\)/', $str, $ms);
			if(isset($ms[1][0])){
				$zone = $this->getzone($ms[1][0]);

				if($zone == null && $arg == 'end'){
					$zone = $this->getset('start',$str)->getTimezone()->getName();
				}
				if($zone == null){
					$zone = $this->zone;
				}

				$v = new DateTime(explode($arg.':',$ms[1][0])[1], new DateTimeZone($zone));
				$this->times[]=$v;
				return $v;
			} 
			return $this->now;
		}

		protected function getzone($str){
			foreach (timezone_identifiers_list() as $z) {
				if (strpos($str,$z)){
					return $z;
				}
			}
			return null;
		}

		protected function gettill(){
			$interval = null;
			foreach ((array)$this->times as $k => $dt){
				if($dt < $this->now){
					unset($this->times[$k]);
				}
			}
			if($this->times){
				$interval =  min($this->times)->getTimestamp() - $this->now->getTimestamp();
			}
			return $interval;
		}

		public function __destruct(){
			
			if($this->till){
				if($this->refresh == 'meta'){
					// meta refresh option:
					$meta = $this->dom->createElement('meta');
					$meta->setAttribute("http-equiv",'refresh');
					$meta->setAttribute("content",$this->till+1);
					$this->dom->getElementsByTagName('head')->item(0)->appendChild($meta);
				 	$this->html = $this->dom->saveHTML();
				}

				if($this->refresh == 'header'){
					// header refresh option:
					header('Refresh:'.$this->till+1);
				}
			}
			echo $this->html;
		}

	}
	$makeitso = new querykit('datetime',$key,'America/New_York','meta');
}
?>