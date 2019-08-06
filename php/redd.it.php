<?php
require_once 'cache.php';

# Limit our database to ~512 KiB per packet.
const PACKET_SIZE = 1024*512;

$cache_enabled = true;
$range_enabled = false;

$client_headers = getallheaders();

$url_parts = parse_url($_SERVER['REQUEST_URI']);
$file = substr($url_parts['path'], 1);

$url = ('https://' . $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI']);

# Checks the url to see if it meets a common file format, eg .png, .gif
# and sets the approprate header
if (!set_content_type($file)) {
	# We aren't serving a specific file, so it's not worth caching.
	# Usually this means someone used a mobile v.redd.it/somepost URL
	$cache_enabled = false;
}

# Pass metadata headers to the server
$curl_headers = array();
foreach ($client_headers as $key => $value) {
	# Don't pass Range headers to the host server, we want the whole file.
	if ($key !== 'Range') {
		$curl_headers[] = ($key . ': ' . $value);
	}
}

# Early exit, simply act as a proxy
if (!$cache_enabled) {
	echo(get($url, $curl_headers));
	die();
}


$cache = new Cache();

if (isset($client_headers['Range'])) {
	[$start, $end] = decode_range($client_headers['Range']);

	# In testing, VLC sent "bytes=0-", so this is to avoid that.
	if (isset($end) && $end !== '') {
		$requested_length = ($end-$start+1);
		$range_enabled = true;
	}
}

if ($cache->is_cached($file)) {
	# File is cached
	if (!$range_enabled) {
		$cache->update_views($file);

		$stmt = $cache->prepare('SELECT `contents`, `length` FROM `RedditCache`.`redd.it` WHERE `file`=?');
		$stmt->bind_param('s', $file);
		$stmt->execute();

		$result = $stmt->get_result();
		$row = $result->fetch_assoc();
		echo($row['contents']);

	} else {
		if ($start === 0) {
			$cache->update_views();
		}
		$start_sql = $start + 1;

		$stmt = $cache->prepare('SELECT SUBSTRING(`contents`,?,?) AS `contents`, `length` FROM `RedditCache`.`redd.it` WHERE `file`=?');
		# MariaDB is one indexed, not zero
		$stmt->bind_param('iis', $start_sql, $requested_length, $file);
		$stmt->execute();

		$result = $stmt->get_result();
		$row = $result->fetch_assoc();

		header('Content-Range: bytes ' . $start . '-' . $end . '/' . $row['length']);

		echo($row['contents']);
	}

} else {
	# File is not cached
	$data = get($url, $curl_headers);
	$length = strlen($data);
	$null = NULL;

	$stmt = $cache->prepare('INSERT INTO `RedditCache`.`redd.it` (`file`, `length`, `contents`) VALUES (?, ?, ?)');
	$stmt->bind_param('sib', $file, $length, $null);

	#Split into 512 KiB chunks
	$chunked = str_split($data, PACKET_SIZE);

	for ($i=0; $i < count($chunked); $i++) { 
		$stmt->send_long_data(2, $chunked[$i]);	
	}

	$stmt->execute();

	if ($range_enabled) {
		header('Content-Range: bytes ' . $start . '-' . $end . '/' . $length);

		echo(substr($data, $start, $requested_length));	
	} else {
		echo($data);
	}
}
