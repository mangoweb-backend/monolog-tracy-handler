#!/usr/bin/env php
<?php declare(strict_types = 1);

$args = array_slice($_SERVER['argv'], 1);

$uploadFile = null;
for ($i = 0, $n = count($args); $i < $n; $i++) {
	if ($args[$i] === '--upload-file' && isset($args[$i + 1])) {
		$uploadFile = $args[$i + 1];
		break;
	}
}

if ($uploadFile === null) {
	exit(1);
}

$sleepUs = (int) (getenv('FAKE_CURL_SLEEP_US') ?: '0');
$outputTarget = getenv('FAKE_CURL_OUTPUT');
$pathRecord = getenv('FAKE_CURL_UPLOAD_PATH');

if ($sleepUs > 0) {
	usleep($sleepUs);
}

$content = @file_get_contents($uploadFile);

if ($content === false) {
	exit(2);
}

if ($outputTarget !== false && $outputTarget !== '') {
	file_put_contents($outputTarget, $content);
}

if ($pathRecord !== false && $pathRecord !== '') {
	file_put_contents($pathRecord, $uploadFile);
}

exit(0);
