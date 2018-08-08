<?php declare(strict_types = 1);

namespace Mangoweb\MonologTracyHandler\MonologProcessors;


class TracyFileNameProcessor
{
	/** @var string */
	private $localBlueScreenDirectory;


	public function __construct(string $localBlueScreenDirectory)
	{
		$this->localBlueScreenDirectory = $localBlueScreenDirectory;
	}


	public function __invoke(array $record): array
	{
		if (isset($record['context']['exception']) && $record['context']['exception'] instanceof \Throwable) {
			$record['context']['tracy_filename'] = $this->computeFileName($record['context']['exception']);
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
				get_class($exception), $exception->getMessage(), $exception->getCode(), $exception->getFile(), $exception->getLine(),
				array_map(function ($item) { unset($item['args']); return $item; }, $exception->getTrace()),
			];
			$exception = $exception->getPrevious();
		}

		$hash = substr(md5(serialize($data)), 0, 10);
		$dir = strtr($this->localBlueScreenDirectory . '/', '\\/', DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR);
		foreach (new \DirectoryIterator($this->localBlueScreenDirectory) as $file) {
			if (strpos($file->getBasename(), $hash)) {
				return $dir . $file;
			}
		}

		return 'exception--' . date('Y-m-d--H-i') . "--$hash.html";
	}
}
