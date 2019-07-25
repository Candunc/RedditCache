<?php
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

		/*
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
		*/

		# Not sure if this is even 100% necessary.
		$this->conn->query("
			CREATE TABLE IF NOT EXISTS `reddit`.`warnings` (
				`timestamp` DATETIME NOT NULL DEFAULT current_timestamp(),
				`warning` TEXT NOT NULL COLLATE 'utf8mb4_bin'
			)
			COLLATE='utf8mb4_bin'
			ENGINE=InnoDB
			;");
	}

	public function __destruct() {
		if (mysqli_warning_count($this->conn) > 0) {
			$stmt = $this->conn->prepare("INSERT INTO `reddit`.`warnings` (`warning`) VALUES (?)");
			$warnings = mysqli_get_warnings($this->conn);
			$stmt->bind_param('s', $warnings);
			$stmt->execute();
		}

		$this->conn->close();
	}

	public function prepare($query) {
		return $this->conn->prepare($query);
	}
}

# https://stackoverflow.com/q/4372710/1687505
function get($url, $headers = NULL) {
	$headers = array();

	$options = array(
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_HEADER         => false,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_ENCODING       => "",
		CURLOPT_AUTOREFERER    => true,
		CURLOPT_CONNECTTIMEOUT => 120,
		CURLOPT_TIMEOUT        => 120,
		CURLOPT_MAXREDIRS      => 10
	);

	if (isset($headers) && !is_null($headers)) {
		$options[CURLOPT_HTTPHEADER] = $headers;
	}

	$ch = curl_init();

	# https://stackoverflow.com/a/41135574
	curl_setopt($ch, CURLOPT_HEADERFUNCTION,
		function($curl, $header) use (&$headers) {
			$len = strlen($header);
			$header = explode(':', $header, 2);
			if (count($header) < 2) { // ignore invalid headers
				return $len;
			}

			$name = strtolower(trim($header[0]));
			if (!array_key_exists($name, $headers)) {
				$headers[$name] = [trim($header[1])];
			} else {
				$headers[$name][] = trim($header[1]);
			}

			return $len;
		});

	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

	curl_setopt_array($ch, $options);
	$content = curl_exec($ch);
	curl_close($ch);

	if (isset($headers["location"])) {
		header("Location: " . $headers["location"][0], true, 302);
	}

	return $content;
}

# https://stackoverflow.com/a/4366748
function contains_substring($string, $substring) {
	return (strpos($string, $substring) !== false);
}

function decode_range($range) {
	return explode("-", substr($range,6));
}
