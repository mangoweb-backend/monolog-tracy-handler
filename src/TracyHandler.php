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
	private ?string $lastMessage = null;

	private ?array $lastContext = null;

	private const UPLOADED_FILE_CONTENTS = 'Uploaded to remote storage.';


	public function __construct(
		private string $localBlueScreenDirectory,
		private ?RemoteStorageDriver $remoteStorageDriver = null,
		Level $level = Level::Debug,
		bool $bubble = true,
		private bool $removeUploads = true,
	) {
		parent::__construct($level, $bubble);
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
			if ($this->remoteStorageDriver !== null) {
				$uploaded = $this->remoteStorageDriver->upload($localPath);
				if ($uploaded && $this->removeUploads) {
					file_put_contents($localPath, self::UPLOADED_FILE_CONTENTS);
				}
			}
		}

		if ($this->removeUploads && $this->remoteStorageDriver !== null) {
			$this->maybeRunGarbageCollection();
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

		$messageHtml = '<h3>' . Tracy\Helpers::escapeHtml($this->lastMessage ?? '') . '</h3>';
		$contextHtml = Tracy\Dumper::toHtml(array_filter($this->lastContext), [
			Tracy\Dumper::DEPTH => Tracy\Debugger::getBlueScreen()->maxDepth,
			Tracy\Dumper::TRUNCATE => Tracy\Debugger::getBlueScreen()->maxLength,
			Tracy\Dumper::LOCATION => Tracy\Dumper::LOCATION_CLASS,
		]);

		return [
			'tab' => 'PSR-3',
			'panel' => "$messageHtml\n$contextHtml",
		];
	}


	private function maybeRunGarbageCollection(): void
	{
		if (rand(0, 100) !== 0) {
			return;
		}

		$deleteOlderThan = new \DateTimeImmutable("-2 days");

		$files = scandir($this->localBlueScreenDirectory);
		if ($files === false) {
			return;
		}
		foreach ($files as $file) {
			$filePath = "{$this->localBlueScreenDirectory}/{$file}";
			if (!is_file($filePath)) {
				continue;
			}

			$date = TracyProcessor::getDateFromFileName($file);
			if ($date !== null && $date < $deleteOlderThan) {
				$fileContents = @file_get_contents($filePath);
				if ($fileContents === self::UPLOADED_FILE_CONTENTS) {
					unlink($filePath);
				}
			}
		}
	}
}
