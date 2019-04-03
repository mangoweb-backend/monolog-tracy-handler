<?php declare(strict_types = 1);

namespace Mangoweb\MonologTracyHandler;

use Mangoweb\Clock\Clock;
use Mangoweb\MonologTracyHandler\RemoteStorageDrivers\NullRemoteStorageDriver;


class TracyProcessor
{
	/** @var RemoteStorageDriver */
	private $remoteStorageDriver;


	public function __construct(?RemoteStorageDriver $remoteStorageDriver = null)
	{
		$this->remoteStorageDriver = $remoteStorageDriver ?? new NullRemoteStorageDriver();
	}


	public function __invoke(array $record): array
	{
		if (isset($record['context']['exception']) && $record['context']['exception'] instanceof \Throwable) {
			$localName = $this->computeFileName($record['context']['exception']);
			$remoteUrl = $this->remoteStorageDriver->getUrl($localName);

			$record['context']['tracy_filename'] = $localName;

			if ($remoteUrl !== null) {
				$record['context']['tracy_url'] = $remoteUrl;
			}
		}

		return $record;
	}


	/**
	 * @see     https://github.com/nette/tracy
	 * @license BSD-3-Clause
	 */
	private function computeFileName(\Throwable $exception): string
	{
		$data = [];

		while ($exception) {
			$data[] = [
				get_class($exception),
				$exception->getMessage(),
				$exception->getCode(),
				$exception->getFile(),
				$exception->getLine(),
				array_map(
					static function (array $item): array {
						unset($item['args']);

						if (strpos($item['class'] ?? '', "\x00") !== false) {
							unset($item['class']);
						}

						return $item;
					},
					$exception->getTrace()
				),
			];

			$exception = $exception->getPrevious();
		}

		$date = Clock::today()->format('Y-m-d');
		$hash = substr(md5(serialize($data)), 0, 10);
		return "exception--$date--$hash.html";
	}
}
