<?php declare(strict_types = 1);

namespace Mangoweb\MonologTracyHandler\MonologProcessors;

use Mangoweb\MonologTracyHandler\RemoteStorageDriver;


class TracyUrlProcessor
{
	/** @var RemoteStorageDriver */
	private $remoteStorageDriver;


	public function __construct(RemoteStorageDriver $remoteStorageDriver)
	{
		$this->remoteStorageDriver = $remoteStorageDriver;
	}


	public function __invoke(array $record): array
	{
		if (isset($record['context']['tracy_filename']) && is_string($record['context']['tracy_filename'])) {
			$localName = $record['context']['tracy_filename'];
			$remoteUrl = $this->remoteStorageDriver->getUrl($localName);

			if ($remoteUrl !== null) {
				$record['context']['tracy_url'] = $remoteUrl;
			}
		}

		return $record;
	}
}
