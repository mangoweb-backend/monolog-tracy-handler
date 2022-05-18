<?php declare(strict_types = 1);

namespace Mangoweb\MonologTracyHandler\RemoteStorageRequestSenders;

use Mangoweb\MonologTracyHandler\RemoteStorageRequestSender;


class ExecCurlRequestSender implements RemoteStorageRequestSender
{
	private string $curlBinary;

	private bool $async;


	public function __construct(string $curlBinary = 'curl', ?bool $async = null)
	{
		$this->curlBinary = $curlBinary;
		$this->async = $async ?? (PHP_SAPI !== 'cli');
	}


	/**
	 * @param array<string, string> $headers
	 */
	public function sendRequest(string $method, string $url, array $headers, string $bodyFilePath): bool
	{
		$args = [$this->curlBinary];

		$args[] = '--request';
		$args[] = $method;

		$args[] = '--url';
		$args[] = $url;

		foreach ($headers as $headerName => $headerValue) {
			$args[] = '--header';
			$args[] = "$headerName: $headerValue";
		}

		$args[] = '--upload-file';
		$args[] = $bodyFilePath;

		$escapedArgs = array_map('escapeshellarg', $args);
		$asyncMarker = $this->async ? ' &' : '';

		$command = implode(' ', $escapedArgs) . ' >/dev/null 2>&1' . $asyncMarker;
		exec($command, $output, $exitCode);

		return $exitCode === 0;
	}
}
