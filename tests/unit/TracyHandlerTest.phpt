<?php declare(strict_types = 1);

namespace MangowebTests\MonologTracyHandler;

use Mangoweb\MonologTracyHandler\RemoteStorageDriver;
use Mangoweb\MonologTracyHandler\TracyHandler;
use Monolog\Level;
use Monolog\LogRecord;
use Tester\Assert;
use Tester\TestCase;


/** @testCase */
(require __DIR__ . '/../bootstrap.php')(
	new class extends TestCase
	{
		private string $logDirectory;


		protected function setUp(): void
		{
			parent::setUp();

			$this->logDirectory = sys_get_temp_dir() . '/monolog-tracy-handler-' . uniqid('', true);
			mkdir($this->logDirectory, 0777, true);
		}


		protected function tearDown(): void
		{
			foreach (scandir($this->logDirectory) ?: [] as $file) {
				if ($file === '.' || $file === '..') {
					continue;
				}

				unlink("{$this->logDirectory}/{$file}");
			}

			rmdir($this->logDirectory);

			parent::tearDown();
		}


		public function testUploadUsesTemporaryCopyBeforeReplacingLocalFile(): void
		{
			$uploadedPath = null;
			$uploadedContents = null;

			$storageDriver = new class($uploadedPath, $uploadedContents) implements RemoteStorageDriver
			{
				public function __construct(
					private ?string &$uploadedPath,
					private ?string &$uploadedContents,
				) {
				}


				public function getUrl(string $localName): ?string
				{
					return null;
				}


				public function upload(string $localPath): bool
				{
					$this->uploadedPath = $localPath;
					$this->uploadedContents = file_get_contents($localPath);

					return true;
				}
			};

			$handler = new class($this->logDirectory, $storageDriver, true, Level::Debug) extends TracyHandler
			{
				public function writeRecord(LogRecord $record): void
				{
					$this->write($record);
				}
			};

			$localPath = "{$this->logDirectory}/trace.html";
			$handler->writeRecord(new LogRecord(
				datetime: new \DateTimeImmutable(),
				channel: 'app',
				level: Level::Error,
				message: 'Boom',
				context: ['exception' => new \Exception('Boom')],
				extra: ['tracy_filename' => 'trace.html'],
			));

			Assert::same('Uploaded to remote storage.', file_get_contents($localPath));
			Assert::type('string', $uploadedPath);
			Assert::notSame($localPath, $uploadedPath);
			Assert::type('string', $uploadedContents);
			Assert::notSame('Uploaded to remote storage.', $uploadedContents);
			Assert::contains('<!DOCTYPE html>', $uploadedContents);
			Assert::false(file_exists($uploadedPath));
		}
	}
);
