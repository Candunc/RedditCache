<?php
require_once "common.php";

# Limit our database to ~512 KiB per packet.
const PACKET_SIZE = 1024*512;
const LOG_METADATA = false;

$disable_cache = false;

$client_headers = getallheaders();

$parts = parse_url($_SERVER['REQUEST_URI']);
$file = substr($parts["path"], 1);

$url = ('https://' . $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI']);

if (contains_substring($file, ".jpg")) {
	header("Content-type: image/jpeg");

} elseif (contains_substring($file, ".png") || contains_substring($file, ".gif?format=png8")) {
	header("Content-type: image/png");

} elseif (contains_substring($file, ".gif")) {
	header("Content-type: image/gif");

} elseif (contains_substring($file, ".m3u8")) {
	header("Content-type: application/x-mpegURL");

} elseif (contains_substring($file, ".ts")) {
	header("Content-type: video/MP2T");

} else {
	$disable_cache = true;
	header("Content-type: text/html");
}

# Disable caching root page or favicon
if ($file === "" || $file === "favicon.ico") {
	$disable_cache = true;
}

$curl_headers = array();
foreach ($client_headers as $key => $value) {
	$curl_headers[] = ($key . ': ' . $value);
}

if ($disable_cache) {
	echo(get($url, $curl_headers));
	die();
}

$db = new Database();

$stmt = $db->prepare("SELECT `contents` FROM `reddit`.`redd.it` WHERE `file`=?");
$stmt->bind_param('s', $file);
$stmt->execute();

$result = $stmt->get_result();
if ($result->num_rows === 0) {
	# Media is not cached!

	$data = get($url, $curl_headers);
	$null = NULL;

	$stmt = $db->prepare("INSERT INTO `reddit`.`redd.it` (`file`, `contents`) VALUES (?, ?)");
	$stmt->bind_param('sb', $file, $null);

	#Split into 512 KiB chunks
	$chunked = str_split($data, PACKET_SIZE);

	for ($i=0; $i < count($chunked); $i++) { 
		$stmt->send_long_data(1, $chunked[$i]);	
	}

	$stmt->execute();
} else {
	$stmt = $db->prepare("UPDATE `reddit`.`redd.it` SET views = views + 1 WHERE `file` = ?");

	$stmt->bind_param('s', $file);
	$stmt->execute();

	$data = $result->fetch_assoc()["contents"];
}

# Check if client is requesting only a range of data
if (isset($headers["Range"])) {
	[$start, $end] = decode_range($headers["Range"]);

	if (!isset($end) || $end === "") {
		echo($data);
	} else {
		header("Content-Range: bytes " . $start . "-" . $end . "/" . strlen($data));

		echo(substr($data, $start, ($end-$start+1)));	
	}
} else {
	echo($data);
}
