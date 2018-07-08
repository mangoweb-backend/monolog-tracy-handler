<?php declare(strict_types = 1);

namespace Mangoweb\MangoLogger\MonologProcessors;

use Mangoweb\MangoLogger\RemoteStorageDriver;


class BlueScreenUploadProcessor
{
	/** @var string */
	private $localBlueScreenDirectory;

	/** @var RemoteStorageDriver */
	private $remoteStorageDriver;


	public function __construct(string $localBlueScreenDirectory, RemoteStorageDriver $remoteStorageDriver)
	{
		$this->localBlueScreenDirectory = $localBlueScreenDirectory;
		$this->remoteStorageDriver = $remoteStorageDriver;
	}


	public function __invoke(array $record): array
	{
		if (isset($record['context']['tracy_filename'], $record['context']['tracy_created'], $record['context']['tracy_url'])) {
			if ($record['context']['tracy_created']) {
				$localName = $record['context']['tracy_filename'];
				$localPath = "{$this->localBlueScreenDirectory}/{$localName}";
				$this->remoteStorageDriver->upload($localPath);
			}
		}

		return $record;
	}
}
