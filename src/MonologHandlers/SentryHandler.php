<?php declare(strict_types = 1);

namespace Mangoweb\MangoLogger\MonologHandlers;

use Mangoweb\SentryLogger\SentryLogger;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;


class SentryHandler extends AbstractProcessingHandler
{
	/** @var SentryLogger */
	private $logger;


	public function __construct(SentryLogger $logger, int $level = Logger::DEBUG, bool $bubble = true)
	{
		parent::__construct($level, $bubble);
		$this->logger = $logger;
	}


	protected function write(array $record): void
	{
		$this->logger->log(strtolower($record['level_name']), $record['message'], $record['context']);
	}
}
