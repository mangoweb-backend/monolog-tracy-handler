<?php declare(strict_types = 1);

namespace MangowebTests\MonologTracyHandler\RemoteStorageRequestSenders;

use Mangoweb\MonologTracyHandler\RemoteStorageRequestSenders\ExecCurlRequestSender;
use Tester\Assert;
use Tester\TestCase;


/** @testCase */
(require __DIR__ . '/../../bootstrap.php')(
	new class extends TestCase
	{
		private string $bodyFile;
		private string $outputFile;
		private string $pathRecordFile;


		protected function setUp(): void
		{
			parent::setUp();

			$this->bodyFile = tempnam(sys_get_temp_dir(), 'body-');
			$this->outputFile = tempnam(sys_get_temp_dir(), 'output-');
			$this->pathRecordFile = tempnam(sys_get_temp_dir(), 'path-');

			putenv("FAKE_CURL_OUTPUT={$this->outputFile}");
			putenv("FAKE_CURL_UPLOAD_PATH={$this->pathRecordFile}");
		}


		protected function tearDown(): void
		{
			@unlink($this->bodyFile);
			@unlink($this->outputFile);
			@unlink($this->pathRecordFile);

			putenv('FAKE_CURL_OUTPUT');
			putenv('FAKE_CURL_UPLOAD_PATH');
			putenv('FAKE_CURL_SLEEP_US');

			parent::tearDown();
		}


		public function testSyncUploadReadsOriginalFile(): void
		{
			file_put_contents($this->bodyFile, 'original-content');

			$sender = new ExecCurlRequestSender(__DIR__ . '/../../fixtures/fake-curl.php', async: false);
			$ok = $sender->sendRequest('PUT', 'http://example/', [], $this->bodyFile);

			Assert::true($ok);
			Assert::same('original-content', file_get_contents($this->outputFile));
			Assert::same($this->bodyFile, file_get_contents($this->pathRecordFile));
		}


		public function testAsyncUploadUsesTempCopyAndSurvivesOverwrite(): void
		{
			file_put_contents($this->bodyFile, 'original-content');
			putenv('FAKE_CURL_SLEEP_US=300000');

			$sender = new ExecCurlRequestSender(__DIR__ . '/../../fixtures/fake-curl.php', async: true);
			$ok = $sender->sendRequest('PUT', 'http://example/', [], $this->bodyFile);

			Assert::true($ok);

			// Simulate TracyHandler truncating the local file while the
			// backgrounded curl is still "uploading".
			file_put_contents($this->bodyFile, 'overwritten');

			$this->waitForFile($this->outputFile, minBytes: 1);
			$this->waitForFile($this->pathRecordFile, minBytes: 1);

			Assert::same('original-content', file_get_contents($this->outputFile));

			$recordedPath = file_get_contents($this->pathRecordFile);
			Assert::type('string', $recordedPath);
			Assert::notSame('', $recordedPath);
			Assert::notSame($this->bodyFile, $recordedPath);
		}


		public function testAsyncUploadCleansUpTempCopy(): void
		{
			file_put_contents($this->bodyFile, 'original-content');
			putenv('FAKE_CURL_SLEEP_US=100000');

			$sender = new ExecCurlRequestSender(__DIR__ . '/../../fixtures/fake-curl.php', async: true);
			$sender->sendRequest('PUT', 'http://example/', [], $this->bodyFile);

			$this->waitForFile($this->outputFile, minBytes: 1);
			$this->waitForFile($this->pathRecordFile, minBytes: 1);

			$tempPath = file_get_contents($this->pathRecordFile);
			Assert::type('string', $tempPath);
			Assert::notSame('', $tempPath);

			// rm happens in the background shell after curl; give it a beat.
			$deadline = microtime(true) + 2.0;
			while (file_exists($tempPath) && microtime(true) < $deadline) {
				usleep(20_000);
			}

			Assert::false(file_exists($tempPath));
		}


		private function waitForFile(string $path, int $minBytes): void
		{
			$deadline = microtime(true) + 5.0;

			while (microtime(true) < $deadline) {
				clearstatcache(true, $path);
				if (is_file($path) && filesize($path) >= $minBytes) {
					return;
				}
				usleep(20_000);
			}
		}
	}
);
