<?php
require_once "common.php";

# Limit our database to ~512 KiB per packet.
const PACKET_SIZE = 1024*512;

$disable_cache = false;

$client_headers = getallheaders();

$parts = parse_url($_SERVER['REQUEST_URI']);
$file = substr($parts["path"], 1);

$url = ('https://' . $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI']);

if (!set_content_type($file)) {
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

$enable_range = false;
if (isset($headers["Range"])) {
	[$start, $end] = decode_range($headers["Range"]);
	if (isset($end) && $end !== "") {
		
	}
}


$stmt = $db->prepare("SELECT `contents`, `length` FROM `RedditCache`.`redd.it` WHERE `file`=?");
$stmt->bind_param('s', $file);
$stmt->execute();

$result = $stmt->get_result();
if ($result->num_rows === 0) {
	# Media is not cached!

	$data = get($url, $curl_headers);
	$length = strlen($data);
	$null = NULL;

	$stmt = $db->prepare("INSERT INTO `RedditCache`.`redd.it` (`file`, `length`, `contents`) VALUES (?, ?, ?)");
	$stmt->bind_param('sib', $file, $length, $null);

	#Split into 512 KiB chunks
	$chunked = str_split($data, PACKET_SIZE);

	for ($i=0; $i < count($chunked); $i++) { 
		$stmt->send_long_data(2, $chunked[$i]);	
	}

	$stmt->execute();
} else {
	$stmt = $db->prepare("UPDATE `RedditCache`.`redd.it` SET views = views + 1 WHERE `file` = ?");

	$stmt->bind_param('s', $file);
	$stmt->execute();

	$row = $result->fetch_assoc();
	$data = $row["contents"];
	$length = $row["length"];
}

# Check if client is requesting only a range of data
if (isset($headers["Range"])) {
	[$start, $end] = decode_range($headers["Range"]);

	if (!isset($end) || $end === "") {
		echo($data);
	} else {
		header("Content-Range: bytes " . $start . "-" . $end . "/" . $length);

		echo(substr($data, $start, ($end-$start+1)));	
	}
} else {
	echo($data);
}
