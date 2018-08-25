<?php declare(strict_types = 1);

namespace Mangoweb\MonologTracyHandler;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;
use Tracy;


class TracyHandler extends AbstractProcessingHandler
{
	/** @var string */
	private $localBlueScreenDirectory;

	/** @var RemoteStorageDriver */
	private $remoteStorageDriver;


	public function __construct(
		string $localBlueScreenDirectory,
		RemoteStorageDriver $remoteStorageDriver,
		int $level = Logger::DEBUG,
		bool $bubble = true
	) {
		parent::__construct($level, $bubble);
		$this->localBlueScreenDirectory = $localBlueScreenDirectory;
		$this->remoteStorageDriver = $remoteStorageDriver;
	}


	protected function write(array $record): void
	{
		if (!isset($record['context']['exception']) || !$record['context']['exception'] instanceof \Throwable) {
			return;
		}

		if (!isset($record['context']['tracy_filename']) || !is_string($record['context']['tracy_filename'])) {
			return;
		}

		$exception = $record['context']['exception'];
		$localName = $record['context']['tracy_filename'];
		$localPath = "{$this->localBlueScreenDirectory}/{$localName}";

		if (is_file($localPath)) {
			return;
		}

		$lockHandle = @fopen("$localPath.lock", 'x');
		if ($lockHandle === false) {
			return;
		}

		Tracy\Debugger::getBlueScreen()->renderToFile($exception, $localPath);
		$this->remoteStorageDriver->upload($localPath);
		fclose($lockHandle);
		@unlink("$localPath.lock");
	}
}
