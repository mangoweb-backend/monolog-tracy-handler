<?php declare(strict_types = 1);

namespace Mangoweb\MangoLogger;


interface RemoteStorageRequestSender
{
	public function sendRequest(string $method, string $url, array $headers, $bodyResource): void;
}
