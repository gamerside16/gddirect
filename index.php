<?php

if (isset($_GET['id'])) {
	gdrive($_GET['id']);
} else if (isset($_GET['a'])) {
	$url = $_GET['a'];
	$index = 0;
	if (isset($_GET['i']))
		$index = $_GET['i'];
	
	if (isset($_GET['id'])) {
		gdrive($_GET['id']);
	} else {
		get($url, $index);
	}
} else {
	http_response_code(404);
	die('Invalid URL');
}
function get($url, $index) {
	if (strpos($url, 'blogger.com') !== false) {
		$json = blogger($url);
        $url = $json['links'][$index]['play_url'];  
		header('Location:'.$url);
	} else {
		gdrive($url);
	}
}

function blogger($url) {
	$ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 6.1; Win64; x64; rv:69.0) Gecko/20100101 Firefox/69.0");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 50);
    $data = curl_exec($ch);
    curl_close($ch);
	
	$internalErrors = libxml_use_internal_errors(true);
	$dom = new DOMDocument();
	@$dom->loadHTML($data);
	$xpath = new DOMXPath($dom);
	$nlist = $xpath->query("//script");
	$fileurl = $nlist[0]->nodeValue;
	$diix = explode('var VIDEO_CONFIG = ', $fileurl);

	$xix = [];
	$ress = json_decode($diix[1], true);
    $xix['links'] = $ress['streams'];
    $xix['img'] = $ress['thumbnail'];
    return $xix;
}
function gdrive($id) {
	$ch = curl_init("https://drive.google.com/uc?id=$id&authuser=0&export=download");
	curl_setopt_array($ch, array(
		CURLOPT_CUSTOMREQUEST => 'POST',
		CURLOPT_SSL_VERIFYPEER => false,
		CURLOPT_POSTFIELDS => [],
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_ENCODING => 'gzip,deflate',
		CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
		CURLOPT_HTTPHEADER => [
			'accept-encoding: gzip, deflate, br',
			'content-length: 0',
			'content-type: application/x-www-form-urlencoded;charset=UTF-8',
			'origin: https://drive.google.com',
			'user-agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/65.0.3325.181 Safari/537.36',
			'x-client-data: CKG1yQEIkbbJAQiitskBCMS2yQEIqZ3KAQioo8oBGLeYygE=',
			'x-drive-first-party: DriveWebUi',
			'x-json-requested: true'
		]
	));
	$response = curl_exec($ch);
	$response_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	curl_close($ch);
	if ($response_code == '200') {
		$object = json_decode(str_replace(')]}\'', '', $response));
		if (isset($object->downloadUrl)) {
			header('Location:'.$object->downloadUrl);
			//echo $object->downloadUrl;
		} else {
			http_response_code(404);
			die('Not found');
		}
	} else {
		http_response_code(403);
		die('Forbidden');
	}
}
?>
