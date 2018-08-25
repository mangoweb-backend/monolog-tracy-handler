<?php declare(strict_types = 1);

namespace Mangoweb\MonologTracyHandler\MonologProcessors;


class TracyFileNameProcessor
{
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
		for (; $exception; $exception = $exception->getPrevious()) {
			$data[] = [
				get_class($exception), $exception->getMessage(), $exception->getCode(), $exception->getFile(), $exception->getLine(),
				array_map(function ($item) { unset($item['args']); return $item; }, $exception->getTrace()),
			];
		}

		$date = date('Y-m-d');
		$hash = substr(md5(serialize($data)), 0, 10);
		return "exception--$date--$hash.html";
	}
}
