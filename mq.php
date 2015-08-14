<?php
// check if temps is set from the ajax call
if(isset($_REQUEST['mq_temps'])){
	foreach (explode(',',$_REQUEST['mq_temps']) as $temp){
		unlink((string)$temp);
	}
}
elseif(!function_exists('buffermesoft')){

// define temps arr
$temps=[];

// get the set value
function getset($arg,$str){
	preg_match_all('/(s?'.$arg.'.*?)\.?\)/', $str, $ms);
	$v = explode($arg.':',$ms[1][0])[1];
	return $v;
}

// have my way with the temp
function touchthetemp($cssfile){
	// get file content
	$css=file_get_contents((string)$cssfile);

	$arg = 'datetime';
	preg_match_all('/(@'.$arg.'.*?\{)/', $css, $dam);
	print_r($dam[1]);
	foreach ($dam[1] as $dtq){
		$start = getset('start',$dtq);
		if (new DateTime() > new DateTime($start)) {
		    //echo $dtq;
		    //$css=str_replace($dtq, replace, subject)
		}	
	}
	//file_put_contents((string)$cssfile, $css);
}

// generate temp css file from the original
function makemetemp($cssfile){
	$path_parts = pathinfo($cssfile);
	$temp = tempnam($path_parts['dirname'], $path_parts['filename']);
	$data = file_get_contents($cssfile, FILE_USE_INCLUDE_PATH);
	file_put_contents($temp.'.css', $data);
	$temp_parts = pathinfo($temp);
	unlink($temp);
	$css = $path_parts['dirname'].'/'.$temp_parts['filename'].'.css';
	touchthetemp($css);
	return $css;
}

// dom parsing to get the css
function parsemehard($html){
	global $temps;
	$dom = new DOMDocument();
	$dom->loadHTML($html);
	$head = $dom->getElementsByTagName('head')->item(0);
	// get all the links in the head
	$links = $head->getElementsByTagName("link");
	// make array of temp css files, for later removal, and swap the link path to the temps
	foreach($links as $l) {
	    if($l->getAttribute("rel") == "stylesheet") {
	        $temp = makemetemp($l->getAttribute("href"));
	        $temps[]=realpath($temp);
	        $l->setAttribute("href",$temp);
	    }
	}
	// define the script to delete the temp css' and subsequently itself
	$script = 'window.addEventListener("load", mq_del);
	function mq_del(){ 
		var request = new XMLHttpRequest();
		request.open("POST", "'.$_SERVER['PHP_SELF'].'", true);
		request.setRequestHeader("Content-Type", "application/x-www-form-urlencoded; charset=UTF-8");
		request.send("mq_temps='.implode(',',$temps).'");
		var mqthis = document.getElementById("mq_temp");
		mqthis.parentNode.removeChild(mqthis);
	}';
	// add the script
	$dscript = $dom->createElement('script', $script);
	$dscript->setAttribute("id",'mq_temp');
	// append the script above the close body
	$dom->getElementsByTagName('body')->item(0)->appendChild($dscript); 
	$html=$dom->saveHTML();
	return $html;
}

// buffer and exe parse 
function buffermesoft(){
	$path = $_SERVER['PHP_SELF'];
    // buffering...
    ob_start();
    include('..'.$path);
    $html=ob_get_contents();
    ob_end_clean();
    $html = parsemehard($html);
    // output all the things
    echo $html;
    // prevent dupe render
    die();
}
buffermesoft();
}
?>