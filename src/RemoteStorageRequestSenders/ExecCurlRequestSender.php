<?php declare(strict_types = 1);

namespace Mangoweb\MonologTracyHandler\RemoteStorageRequestSenders;

use Mangoweb\MonologTracyHandler\RemoteStorageRequestSender;


class ExecCurlRequestSender implements RemoteStorageRequestSender
{
	/** @var string */
	private $curlBinary;

	/** @var bool */
	private $async;


	public function __construct(string $curlBinary = 'curl', ?bool $async = null)
	{
		$this->curlBinary = $curlBinary;
		$this->async = $async ?? (PHP_SAPI !== 'cli');
	}


	/**
	 * @param array<string, string> $headers
	 * @param resource              $bodyStreamHandle
	 */
	public function sendRequest(string $method, string $url, array $headers, $bodyStreamHandle): void
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

		$args[] = '--data-binary';
		$args[] = stream_get_contents($bodyStreamHandle, -1, 0);

		$asyncMarker = $this->async ? ' &' : '';
		exec(implode(' ', array_map('escapeshellarg', $args)) . ' >/dev/null 2>&1' . $asyncMarker);
	}
}
