<?php declare(strict_types = 1);

namespace Mangoweb\MangoLogger\Bridges\GuzzleHttp;

use Mangoweb\MangoLogger\RemoteStorageRequestSender;


class GuzzleHttpAdapter implements RemoteStorageRequestSender
{
	/** @var \GuzzleHttp\ClientInterface */
	private $guzzleClient;


	public function __construct(\GuzzleHttp\ClientInterface $guzzleClient)
	{
		$this->guzzleClient = $guzzleClient;
	}


	public function sendRequest(string $method, string $url, array $headers, $bodyStreamHandle): void
	{
		try {
			$httpRequest = new \GuzzleHttp\Psr7\Request($method, $url, $headers, $bodyStreamHandle);
			$this->guzzleClient->send($httpRequest);

		} catch (\GuzzleHttp\Exception\GuzzleException $e) {
			// suppress
		}
	}
}
