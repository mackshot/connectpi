<?php

$result = shell_exec("ping google.com -c 5");

if (preg_match("/[\d|\.]+\/([\d|\.]+)\/[\d|\.]+\/[\d|\.]+ ms/", $result, $match)) {
	print json_encode(array(true, "Connected to internet (latency " . $match[1] . " ms)"));
} else {
	print json_encode(array(false, "No internet access"));
}