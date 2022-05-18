<?php declare(strict_types = 1);

namespace MangowebTests\MonologTracyHandler;

use Mangoweb\Clock\ClockMock;
use Mangoweb\MonologTracyHandler\RemoteStorageDriver;
use Mangoweb\MonologTracyHandler\TracyProcessor;
use Mockery;
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

			Assert::same(['a' => 'b'], $processor(['a' => 'b']));
		}


		public function testInvokeWithExceptionNullStorage(): void
		{
			$storageDriver = Mockery::mock(RemoteStorageDriver::class);
			$storageDriver->expects('getUrl')
				->with('exception--2018-10-09--b48e85fdbd.html')
				->andReturnNull();

			$processor = new TracyProcessor($storageDriver);
			$exception = $this->createException();

			Assert::same(
				[
					'a' => 'b',
					'context' => [
						'exception' => $exception,
						'tracy_filename' => 'exception--2018-10-09--b48e85fdbd.html',
					],
				],
				$processor(['a' => 'b', 'context' => ['exception' => $exception]])
			);
		}


		public function testInvokeWithExceptionStorage(): void
		{
			$storageDriver = Mockery::mock(RemoteStorageDriver::class);
			$storageDriver->expects('getUrl')
				->with('exception--2018-10-09--96577eb4c8.html')
				->andReturn('https://example.com/foo.html');

			$processor = new TracyProcessor($storageDriver);
			$exception = $this->createException();

			Assert::same(
				[
					'a' => 'b',
					'context' => [
						'exception' => $exception,
						'tracy_filename' => 'exception--2018-10-09--96577eb4c8.html',
						'tracy_url' => 'https://example.com/foo.html',
					],
				],
				$processor(['a' => 'b', 'context' => ['exception' => $exception]])
			);
		}


		private function createException(): \Exception
		{
			$exception = new \Exception();
			$reflection = new \ReflectionClass($exception);

			$filePropertyReflection = $reflection->getProperty('file');
			$filePropertyReflection->setAccessible(true);
			$filePropertyReflection->setValue($exception, '/src/foo/bar.txt');

			$linePropertyReflection = $reflection->getProperty('line');
			$linePropertyReflection->setAccessible(true);
			$linePropertyReflection->setValue($exception, 123);

			$tracyPropertyReflection = $reflection->getProperty('trace');
			$tracyPropertyReflection->setAccessible(true);
			$tracyPropertyReflection->setValue($exception, array_map(
				static function (array $frame): array {
					$frame['file'] = strtr(str_replace(dirname(__FILE__, 3), '', $frame['file'] ?? ''), '\\', '/');
					$frame['line'] = 123;
					return $frame;
				},
				$exception->getTrace()
			));

			return $exception;
		}
	}
);
