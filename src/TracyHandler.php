<?php declare(strict_types = 1);

namespace Mangoweb\MonologTracyHandler;

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
		if (!isset($record['context']['exception']) || !$record['context']['exception'] instanceof Throwable) {
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

		$lockPath = "$localPath.lock";
		$lockHandle = @fopen($lockPath, 'x');
		if ($lockHandle === false) {
			return;
		}

		$this->lastMessage = $record['message'];
		$this->lastContext = $record['context'];
		Tracy\Debugger::getBlueScreen()->addPanel([$this, 'renderPsrLogPanel']);
		Tracy\Debugger::getBlueScreen()->renderToFile($exception, $localPath);
		$this->remoteStorageDriver->upload($localPath);

		@fclose($lockHandle);
		@unlink($lockPath);
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
			Tracy\Dumper::LIVE => true,
			Tracy\Dumper::LOCATION => Tracy\Dumper::LOCATION_CLASS,
		]);

		$this->lastMessage = null;
		$this->lastContext = null;

		return [
			'tab' => 'PSR-3',
			'panel' => "$messageHtml\n$contextHtml",
		];
	}
}
