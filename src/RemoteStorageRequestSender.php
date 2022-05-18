<?php declare(strict_types = 1);

namespace Mangoweb\MonologTracyHandler;


interface RemoteStorageRequestSender
{
	/**
	 * @param array<string, string> $headers
	 */
	public function sendRequest(string $method, string $url, array $headers, string $bodyFilePath): bool;
}
