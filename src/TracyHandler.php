<?php declare(strict_types = 1);

namespace Mangoweb\MonologTracyHandler;

use Mangoweb\MonologTracyHandler\RemoteStorageDrivers\NullRemoteStorageDriver;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;
use Throwable;
use Tracy;


class TracyHandler extends AbstractProcessingHandler
{
	/** @var string */
	private $localBlueScreenDirectory;

	/** @var RemoteStorageDriver */
	private $remoteStorageDriver;

	/** @var null|string */
	private $lastMessage;

	/** @var null|array */
	private $lastContext;


	public function __construct(
		string $localBlueScreenDirectory,
		?RemoteStorageDriver $remoteStorageDriver = null,
		int $level = Logger::DEBUG,
		bool $bubble = true
	) {
		parent::__construct($level, $bubble);
		$this->localBlueScreenDirectory = $localBlueScreenDirectory;
		$this->remoteStorageDriver = $remoteStorageDriver ?? new NullRemoteStorageDriver();
	}


	protected function write(array $record): void
	{
		if (!isset($record['context']['exception']) || !$record['context']['exception'] instanceof Throwable) {
			return;
		}

		if (!isset($record['context']['tracy_filename']) || !is_string($record['context']['tracy_filename'])) {
			return;
		}

		$this->lastMessage = $record['message'];
		$this->lastContext = $record['context'];

		$exception = $record['context']['exception'];
		$localName = $record['context']['tracy_filename'];
		$localPath = "{$this->localBlueScreenDirectory}/{$localName}";

		$blueScreen = Tracy\Debugger::getBlueScreen();
		$blueScreen->addPanel([$this, 'renderPsrLogPanel']);

		if (!is_dir($this->localBlueScreenDirectory)) {
			mkdir($this->localBlueScreenDirectory, 0777, true);
		}

		if ($blueScreen->renderToFile($exception, $localPath)) {
			$this->remoteStorageDriver->upload($localPath);
		}

		$this->lastMessage = null;
		$this->lastContext = null;
	}


	public function renderPsrLogPanel(?Throwable $e): ?array
	{
		if ($this->lastContext === null || $e !== $this->lastContext['exception']) {
			return null;
		}

		unset($this->lastContext['tracy_filename'], $this->lastContext['tracy_url']);
		$this->lastContext = array_filter($this->lastContext);

		$messageHtml = '<h3>' . Tracy\Helpers::escapeHtml($this->lastMessage ?? '') . '</h3>';
		$contextHtml = Tracy\Dumper::toHtml($this->lastContext, [
			Tracy\Dumper::DEPTH => Tracy\Debugger::getBlueScreen()->maxDepth,
			Tracy\Dumper::TRUNCATE => Tracy\Debugger::getBlueScreen()->maxLength,
			Tracy\Dumper::LOCATION => Tracy\Dumper::LOCATION_CLASS,
		]);

		return [
			'tab' => 'PSR-3',
			'panel' => "$messageHtml\n$contextHtml",
		];
	}
}
