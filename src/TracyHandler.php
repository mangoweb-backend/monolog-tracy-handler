<?php declare(strict_types = 1);

namespace Mangoweb\MonologTracyHandler;

use Mangoweb\MonologTracyHandler\RemoteStorageDrivers\NullRemoteStorageDriver;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;
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
		Level $level = Level::Debug,
		bool $bubble = true
	) {
		parent::__construct($level, $bubble);
		$this->localBlueScreenDirectory = $localBlueScreenDirectory;
		$this->remoteStorageDriver = $remoteStorageDriver ?? new NullRemoteStorageDriver();
	}


	protected function write(LogRecord $record): void
	{
		if (!isset($record->context['exception']) || !$record->context['exception'] instanceof Throwable) {
			return;
		}

		if (!isset($record->extra['tracy_filename']) || !is_string($record->extra['tracy_filename'])) {
			return;
		}

		$this->lastMessage = $record->message;
		$this->lastContext = $record->context;

		$exception = $record->context['exception'];
		$localName = $record->extra['tracy_filename'];
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


	/**
	 * @return ?array{tab: string, panel: string}
	 */
	public function renderPsrLogPanel(?Throwable $e): ?array
	{
		if ($this->lastContext === null || $e !== $this->lastContext['exception']) {
			return null;
		}

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
