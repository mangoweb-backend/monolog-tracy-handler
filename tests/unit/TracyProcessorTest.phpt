<?php declare(strict_types = 1);

namespace MangowebTests\MonologTracyHandler;

use Mangoweb\Clock\Clock;
use Mangoweb\Clock\ClockMock;
use Mangoweb\MonologTracyHandler\RemoteStorageDriver;
use Mangoweb\MonologTracyHandler\TracyProcessor;
use Mockery;
use Monolog\Level;
use Monolog\LogRecord;
use Tester\Assert;
use Tester\TestCase;


/** @testCase */
(require __DIR__ . '/../bootstrap.php')(
	new class extends TestCase
	{
		protected function setUp(): void
		{
			parent::setUp();
			ClockMock::mockNow('2018-10-09 21:32');
		}


		protected function tearDown(): void
		{
			parent::tearDown();
			Mockery::close();
		}


		public function testInvokeWithRecordWithoutException(): void
		{
			$storageDriver = Mockery::mock(RemoteStorageDriver::class);
			$processor = new TracyProcessor($storageDriver);

			Assert::same(
				[
					'message' => 'Hello',
					'context' => ['a' => 'b'],
					'level' => 100,
					'level_name' => 'DEBUG',
					'channel' => 'app',
					'datetime' => Clock::now(),
					'extra' => [],
				],
				$processor(new LogRecord(
					datetime: Clock::now(),
					channel: 'app',
					level: Level::Debug,
					message: 'Hello',
					context: ['a' => 'b'],
				))->toArray(),
			);
		}


		public function testInvokeWithExceptionNullStorage(): void
		{
			$exception = $this->createException();
			$expectedFileName = 'exception--2018-10-09--acff8ba9d4.html';

			$storageDriver = Mockery::mock(RemoteStorageDriver::class);
			$storageDriver->expects('getUrl')
				->with($expectedFileName)
				->andReturnNull();

			$processor = new TracyProcessor($storageDriver);

			Assert::same(
				[
					'message' => 'Hello',
					'context' => [
						'exception' => $exception,
					],
					'level' => 100,
					'level_name' => 'DEBUG',
					'channel' => 'app',
					'datetime' => Clock::now(),
					'extra' => [
						'tracy_filename' => $expectedFileName,
					],
				],
				$processor(new LogRecord(
					datetime: Clock::now(),
					channel: 'app',
					level: Level::Debug,
					message: 'Hello',
					context: ['exception' => $exception],
				))->toArray(),
			);
		}


		public function testInvokeWithExceptionStorage(): void
		{
			$exception = $this->createException();
			$expectedFileName = 'exception--2018-10-09--acff8ba9d4.html';

			$storageDriver = Mockery::mock(RemoteStorageDriver::class);
			$storageDriver->expects('getUrl')
				->with($expectedFileName)
				->andReturn('https://example.com/foo.html');

			$processor = new TracyProcessor($storageDriver);

			Assert::same(
				[
					'message' => 'Hello',
					'context' => [
						'exception' => $exception,
					],
					'level' => 100,
					'level_name' => 'DEBUG',
					'channel' => 'app',
					'datetime' => Clock::now(),
					'extra' => [
						'tracy_filename' => $expectedFileName,
						'tracy_url' => 'https://example.com/foo.html',
					]
				],
				$processor(new LogRecord(
					datetime: Clock::now(),
					channel: 'app',
					level: Level::Debug,
					message: 'Hello',
					context: ['exception' => $exception],
				))->toArray(),
			);
		}


		public function testFileNameParsing(): void
		{
			Assert::same('2018-10-09T00:00:00+00:00', TracyProcessor::getDateFromFileName('exception--2018-10-09--96577eb4c8.html')->format('c'));

			Assert::null(TracyProcessor::getDateFromFileName('exception--2018-10-09--96577eb4c8EXTRA.html'));
			Assert::null(TracyProcessor::getDateFromFileName('exception--2018-10-09--96577eb4c8'));
			Assert::null(TracyProcessor::getDateFromFileName('exception--2018-10-09--96577eb4c8.'));
			Assert::null(TracyProcessor::getDateFromFileName('exception--2018-10-09--96577eb4c8.json'));
			Assert::null(TracyProcessor::getDateFromFileName('.'));
			Assert::null(TracyProcessor::getDateFromFileName('directory'));
			Assert::null(TracyProcessor::getDateFromFileName('exception--2018-10-09--96577eb4c8.htmlEXTRA'));
			Assert::null(TracyProcessor::getDateFromFileName('EXTRAexception--2018-10-09--96577eb4c8.html'));

			Assert::same('2026-03-09T00:00:00+00:00', TracyProcessor::getDateFromFileName('exception--2018-99-09--96577eb4c8.html')->format('c'));
			Assert::same('2019-01-07T00:00:00+00:00', TracyProcessor::getDateFromFileName('exception--2018-10-99--96577eb4c8.html')->format('c'));
		}


		private function createException(): \Exception
		{
			$exception = new \Exception();
			$reflection = new \ReflectionClass($exception);

			$reflection->getProperty('file')->setValue($exception, '/src/foo/bar.txt');
			$reflection->getProperty('line')->setValue($exception, 123);
			$reflection->getProperty('trace')->setValue($exception, []);

			return $exception;
		}
	}
);
