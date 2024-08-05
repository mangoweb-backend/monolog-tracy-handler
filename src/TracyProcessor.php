<?php declare(strict_types = 1);

namespace Mangoweb\MonologTracyHandler;

use DateTimeImmutable;
use DateTimeZone;
use Mangoweb\Clock\Clock;
use Mangoweb\MonologTracyHandler\RemoteStorageDrivers\NullRemoteStorageDriver;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;


class TracyProcessor implements ProcessorInterface
{
	private RemoteStorageDriver $remoteStorageDriver;


	public function __construct(?RemoteStorageDriver $remoteStorageDriver = null)
	{
		$this->remoteStorageDriver = $remoteStorageDriver ?? new NullRemoteStorageDriver();
	}


	public function __invoke(LogRecord $record): LogRecord
	{
		if (isset($record->context['exception']) && $record->context['exception'] instanceof \Throwable) {
			$localName = $this->computeFileName($record->context['exception']);
			$remoteUrl = $this->remoteStorageDriver->getUrl($localName);

			$record->extra['tracy_filename'] = $localName;

			if ($remoteUrl !== null) {
				$record->extra['tracy_url'] = $remoteUrl;
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

						if (str_contains($item['class'] ?? '', "\x00")) {
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


	public static function getDateFromFileName(string $fileName): ?DateTimeImmutable
	{
		if (!preg_match('/^exception--(?<date>\d{4}-\d{2}-\d{2})--\w{10}\.html$/', $fileName, $matches)) {
			return null;
		}

		$date = DateTimeImmutable::createFromFormat('!Y-m-d', $matches['date'], new DateTimeZone('UTC'));

		if ($date === false) {
			return null;
		}

		return $date;
	}
}
