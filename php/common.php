<?php
const USER_AGENT = "AppleCoreMedia/1.0.0.16F203 (iPhone; U; CPU OS 12_3_1 like Mac OS X; en_gb)";
require_once "config.php";

class Database {
	public $conn;

	public function __construct() {
		$this->conn = new mysqli(DB_ADDR, DB_USER, DB_PASS, "reddit");

		if ($this->conn->connect_error) {
			die("Connection failed: " . $this->conn->connect_error);
		}

		$this->conn->query("
			CREATE TABLE IF NOT EXISTS `reddit`.`redd.it` (
				`file` VARCHAR(64) NOT NULL COLLATE 'utf8mb4_bin',
				`views` INT(10) UNSIGNED NOT NULL DEFAULT 1,
				`last_update` DATETIME NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
				`contents` MEDIUMBLOB NOT NULL,
				UNIQUE INDEX `Index 1` (`file`)
			)
			COLLATE='utf8mb4_bin'
			ENGINE=InnoDB
			;");

		$this->conn->query("
			CREATE TABLE IF NOT EXISTS `reddit`.`metadata` (
				`timestamp` DATETIME NOT NULL DEFAULT current_timestamp(),
				`url` VARCHAR(64) NOT NULL COLLATE 'utf8mb4_bin',
				`file` VARCHAR(64) NOT NULL COLLATE 'utf8mb4_bin',
				`headers` TEXT NOT NULL COLLATE 'utf8mb4_bin'
			)
			COLLATE='utf8mb4_bin'
			ENGINE=InnoDB
			;");
	}

	public function __destruct() {
		$this->conn->close();
	}

	public function prepare($query) {
		return $this->conn->prepare($query);
	}
}

# https://stackoverflow.com/q/4372710/1687505
function get($url, $headers = NULL) {
	$options = array(
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_HEADER         => false,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_ENCODING       => "",
		CURLOPT_USERAGENT      => USER_AGENT,
		CURLOPT_AUTOREFERER    => true,
		CURLOPT_CONNECTTIMEOUT => 120,
		CURLOPT_TIMEOUT        => 120,
		CURLOPT_MAXREDIRS      => 10
	);

	if (isset($headers) && !is_null($headers)) {
		$options[CURLOPT_HTTPHEADER] = $headers;
	}

	$ch = curl_init($url);
	curl_setopt_array($ch, $options);
	$content = curl_exec($ch);
	curl_close($ch);

	return $content;
}

# https://stackoverflow.com/a/4366748
function contains_substring($string, $substring) {
	return (strpos($string, $substring) !== false);
}

function decode_range($range) {
	return explode("-", substr($range,6));
}
