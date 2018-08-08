<?php declare(strict_types = 1);

namespace Mangoweb\MonologTracyHandler;


interface RemoteStorageRequestSender
{
	public function sendRequest(string $method, string $url, array $headers, $bodyResource): void;
}
