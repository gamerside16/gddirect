<?php
/* Google Drive Stream Proxy
 Code được share free tại các group : J2Team , Yam - ShareNgay , T1Team
 Đồng tác giả : https://github.com/truongsofm & https://github.com/namhong1412
 Thấy hay thì nhớ cho chúng mình 1 Star và 1 Follow github nhé 
 */
declare(strict_types=1);
error_reporting(1);

function cache_path(string $id) : string {
	if (!file_exists('_cache')) {
		mkdir('_cache', 0777);
	}
	
	return '_cache/' . $id;

	/*if (strlen($id) == 33) {
		return '_cache/' . hash('sha256',$id, false);
	} else {
		return '_cache/' . $id;
	}*/
}

function read_data(string $id) {
	$fpath = cache_path($id);
	if ($fhandle = fopen($fpath,'r')) {
		$content = fread($fhandle,filesize($fpath));
		fclose($fhandle);
		return json_decode($content,true);
	} else {
		return null;
	}
}

function write_data(string $id) {
	$fpath = cache_path($id);
	if ($fhandle = fopen($fpath,'w')) {
		
		$sources_list = array();
		$ar_list = array();
		$cookies = '';

		// Check whenever file was available or not
		$ch = curl_init('https://drive.google.com/get_video_info?docid=' . $id);
		curl_setopt_array($ch,array(
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_RETURNTRANSFER => 1
		));
		$x = curl_exec($ch);
		parse_str($x,$x);
		if ($x['status'] == 'fail') {
			return null;
		}
		curl_close($ch);
		
		// Fetch Google Drive File
		$ch = curl_init('https://drive.google.com/get_video_info?docid=' . $id);
		curl_setopt_array($ch,array(
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_RETURNTRANSFER => 1,
			CURLOPT_HEADER => 1
		));
		$result = curl_exec($ch);
		curl_close($ch);

		// Get Cookies
		preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $result, $matches);
		$cookies = array();
		foreach($matches[1] as $item) {
			parse_str($item, $cookie);
			$cookies = array_merge($cookies, $cookie);
		}
		
		// Parse Resolution
		parse_str($result,$data);
		$sources = explode(',',$data['fmt_stream_map']);
		$fname = $data['title'];
		foreach($sources as $source){
			
			switch ((int)substr($source, 0, 2)) {
				case 18:
					$resolution = '360p';
					break;
				case 59:
					$resolution = '480p';
					break;
				case 22:
					$resolution = '720p';
					break;
				case 37:
					$resolution = '1080p';
					break;
			}
			
			$x = substr($source, strpos($source, "|") + 1);
			
			// Get Content-Length of sources
			$curl = curl_init();
			curl_setopt_array($curl, array(
				CURLOPT_URL => substr($source, strpos($source, "|") + 1),
				CURLOPT_HEADER => true,
				CURLOPT_CONNECTTIMEOUT => 0,
				CURLOPT_TIMEOUT => 1000,
				CURLOPT_FRESH_CONNECT => true,
				CURLOPT_SSL_VERIFYPEER => 0,
				CURLOPT_NOBODY => true,
				CURLOPT_VERBOSE => 1,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_HTTPHEADER => array(
					'Connection: keep-alive',
					'Cookie: DRIVE_STREAM=' . $cookies['DRIVE_STREAM']
				)
			));
			
			curl_exec($curl);
			$content_length = curl_getinfo($curl, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
			curl_close($curl);
			
			array_push($sources_list, array(
				'resolution' => $resolution,
				'src' => $x,
				'content-length' => $content_length)
			);
			
			array_push($ar_list, $resolution);
			
		}
		
		// Get thumbnail Image
		$ch = curl_init('https://drive.google.com/thumbnail?authuser=0&sz=w9999&id=' . $id);
		curl_setopt($ch, CURLOPT_HEADER, true);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$result = curl_exec($ch);
		curl_close($ch);
		if (preg_match('~Location: (.*)~i', $result, $match)) {
			$thumbnail = trim($match[1]);
		} else {
			$thumbnail = '';
		}
		
		$json = json_encode(array(
			'thumbnail' => $thumbnail,
			'cookies' => $cookies,
			'sources' => $sources_list,
			'id' => $id,
		));
		
		// Write to file
		fwrite($fhandle, $json);
		fclose($fhandle);

		/*if (in_array('1080p', $ar_list)) {
			$stream = '1080p';
		} else if (in_array('720p', $ar_list)) {
			$stream = '720p';
		} else if (in_array('480p', $ar_list)) {
			$stream = '480p';
		} else {
			$stream = '360p';
		}*/
		
		//header('location: ?id='.hash('sha256', $id, false).'&stream='.$stream);
		// return array(
		// 	'hash' => hash('sha256', $id, false),
		// 	'sources' => $ar_list
		// ); // Serve as JSON
		
		return $json;
		
	} else {
		
		return null; // Return null
		
	}
}

function fetch_video(array $data) : int {
	
	$content_length = $data['content-length'];
	$headers = array(
			'Connection: keep-alive',
			'Cookie: DRIVE_STREAM=' . $data['cookie']['DRIVE_STREAM']
	);
	
	if (isset($_SERVER['HTTP_RANGE'])) {
		
		$http = 1;
		preg_match('/bytes=(\d+)-(\d+)?/', $_SERVER['HTTP_RANGE'], $range);
		$initial = intval($range[1]);
		$final = $content_length - $initial - 1;
		array_push($headers,'Range: bytes=' . $initial . '-' . ($initial + $final));
		
	} else {
		
		$http = 0;
		
	}
	
	if ($http == 1) {
		
		header('HTTP/1.1 206 Partial Content');
		header('Accept-Ranges: bytes'); 
		header('Content-Range: bytes ' . $initial . '-' . ($initial + $final) . '/' . $data['content-length']);
		
	} else {
		
		header('Accept-Ranges: bytes'); 
		
	}
	
	$ch = curl_init();
	
	curl_setopt_array($ch, array(
		CURLOPT_URL => $data['src'],
		CURLOPT_CONNECTTIMEOUT => 0,
		CURLOPT_TIMEOUT => 1000,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_FRESH_CONNECT => true,
		CURLOPT_HTTPHEADER => $headers
	));
	
	curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($curl, $body) {
		echo $body;
		return strlen($body);
	});
	
	header('Content-Type: video/mp4');
	header('Content-length: ' . $content_length);
	
	curl_exec($ch);

}
function drc($code) {
    $start = "aHR0cA";
    $code = substr($code, 3, -3);
    $code = substr($code, 0, 3) . substr($code, 6);
    $code = base64_decode($start . "==") . base64_decode($code);
    return $code;
}

function gdrive_getID($url) {
	$filter1 = preg_match('/drive\.google\.com\/open\?id\=(.*)/', $url, $id1);
	$filter2 = preg_match('/drive\.google\.com\/file\/d\/(.*?)\//', $url, $id2);
	$filter3 = preg_match('/drive\.google\.com\/uc\?id\=(.*)/', $url, $id3);
	if($filter1){
		$id = $id1[1];
	} else if($filter2){
		$id = $id2[1];
	} else if($filter3){
		$id = $id3[1];
	} else {
		$id = null;
	}
	
	return($id);
}

$GDriveID = drc($_GET['id']);
$GDriveID = gdrive_getID($GDriveID);

if (isset($GDriveID)) {
	$fdata = read_data($GDriveID);
	$res = '360p';
	if (isset($_GET['stream']))
		$res = $_GET['stream'];
						
	if (strlen($GDriveID) == 33) {
		if ($fdata !== null) {
			if (time()-filemtime(cache_path($GDriveID)) > 3 * 3600) { // Check if file aleardy 3 hours
				$fdata_new = write_data($GDriveID);
				if ($fdata_new !== null) {
					$content = json_decode($fdata_new,true);
					foreach($content['sources'] as $x) {
						if ($x['resolution'] == $res) {
							fetch_video(array(
								'content-length' => $x['content-length'],
								'src' => $x['src'],
								'cookie' => $content['cookies']
							));
							break;
						}
					}
				} else {
					die('Failed write data');
				}
			} else {
				if (is_array($fdata)) { // Check whenver data on file was array
					foreach($fdata['sources'] as $x) {
						if ($x['resolution'] == $res) {
							fetch_video(array(
								'content-length' => $x['content-length'],
								'src' => $x['src'],
								'cookie' => $fdata['cookies']
							));
							break;
						}
					}
				} else { // If not remove it and tell file was corrupt
					unlink(cache_path($GDriveID));
					die('File was corrupt, please re-generate file.');
				}
			}
		} else {
			$fdata_new = write_data($GDriveID);
			if ($fdata_new !== null) {
				$content = json_decode($fdata_new,true);
				foreach($content['sources'] as $x) {
					if ($x['resolution'] == $res) {
						fetch_video(array(
							'content-length' => $x['content-length'],
							'src' => $x['src'],
							'cookie' => $content['cookies']
						));
						break;
					}
				}
			} else {
				die('Failed write data');
			}
		}
	} else {
		die('Invalid ID.');
	}
	
} else {
	die('Invalid ID.');
}

?>
