<?php declare(strict_types = 1);

namespace Mangoweb\MangoLogger\RemoteStorageRequestSenders;

use Mangoweb\MangoLogger\RemoteStorageRequestSender;


class ExecCurlRequestSender implements RemoteStorageRequestSender
{
	/** @var string */
	private $curlBinary;


	public function __construct(string $curlBinary = 'curl')
	{
		$this->curlBinary = $curlBinary;
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

		exec(implode(' ', array_map('escapeshellarg', $args)) . ' >/dev/null 2>&1 &');
	}
}
