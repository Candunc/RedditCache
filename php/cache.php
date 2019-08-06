<?php
require_once 'common.php';

class Cache {
	private $db;

	public function __construct() {
		$this->db = new Database();
	}
	
	public function prepare($query) {
		return $this->db->prepare($query);
	}

	public function is_cached($file) {
		$stmt = $this->db->prepare('SELECT 1 FROM `RedditCache`.`redd.it` WHERE file=?');
		$stmt->bind_param('s', $file);
		$stmt->execute();

		$result = $stmt->get_result();
		return ($result->num_rows === 1);
	}

	public function update_views($file) {
		$stmt = $this->db->prepare("UPDATE `RedditCache`.`redd.it` SET views = views + 1 WHERE `file` = ?");

		$stmt->bind_param('s', $file);
		$stmt->execute();
	}
}

# https://stackoverflow.com/a/4366748
function contains_substring($string, $substring) {
	return (strpos($string, $substring) !== false);
}

# Decodes a range header
# Input is "bytes=<start>-<end>", returns [$start, $end]
function decode_range($range) {
	return explode('-', substr($range,6));
}

# A basic heuristic to guess file type, purely looks for an extension in the URL
function set_content_type($file) {
	$confident_guess = true;

	if (contains_substring($file, '.jpg')) {
		header('Content-type: image/jpeg');

	} elseif (contains_substring($file, '.png') || contains_substring($file, 'format=png8')) {
		header('Content-type: image/png');

	} elseif (contains_substring($file, '.mp4') || contains_substring($file, "format=mp4")) {
		header('Content-type: video/mp4');

	} elseif (contains_substring($file, '.gif')) {
		header('Content-type: image/gif');

	} elseif (contains_substring($file, '.m3u8')) {
		header('Content-type: application/x-mpegURL');

	} elseif (contains_substring($file, '.ts')) {
		header('Content-type: video/MP2T');

	} else {
		$confident_guess = false;
		header('Content-type: text/html');
	}

	return $confident_guess;
}
