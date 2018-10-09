<?php declare(strict_types = 1);

namespace MangowebTests\MonologTracyHandler\RemoteStorageDrivers;

use Mangoweb\Clock\ClockMock;
use Mangoweb\MonologTracyHandler\RemoteStorageDrivers\AwsS3RemoteStorageDriver;
use Mangoweb\MonologTracyHandler\RemoteStorageRequestSender;
use Mockery;
use Tester\Assert;
use Tester\TestCase;


/** @testCase */
(require __DIR__ . '/../../bootstrap.php')(
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


		public function testGetUrl(): void
		{
			$requestSender = Mockery::mock(RemoteStorageRequestSender::class);
			$storageDriver = $this->createStorageDriver($requestSender);

			Assert::same(
				'https://s3.eu-central-1.amazonaws.com/my-app/logs/a5ef95ed6b795b3dfed85238d3003cae.html',
				$storageDriver->getUrl('exception--2018-10-09--144c575abe.html')
			);
		}


		public function testUploadOk(): void
		{
			$requestSender = Mockery::mock(RemoteStorageRequestSender::class);
			$requestSender->expects('sendRequest')
				->andReturnUsing(function (string $method, string $url, array $headers, string $bodyFilePath): bool {
					Assert::same('PUT', $method);
					Assert::same('https://s3.eu-central-1.amazonaws.com/my-app/logs/a5ef95ed6b795b3dfed85238d3003cae.html', $url);
					Assert::same('/src/log/exception--2018-10-09--144c575abe.html', $bodyFilePath);

					Assert::count(7, $headers);
					Assert::same('s3.eu-central-1.amazonaws.com', $headers['Host']);
					Assert::same('MangoLogger', $headers['User-Agent']);
					Assert::same('public-read', $headers['X-Amz-ACL']);
					Assert::same('UNSIGNED-PAYLOAD', $headers['X-Amz-Content-Sha256']);
					Assert::same('20181009T213200Z', $headers['X-Amz-Date']);
					Assert::same('AWS4-HMAC-SHA256 Credential=AKIAIOSFODNN7EXAMPLE/20181009/eu-central-1/s3/aws4_request, SignedHeaders=host;user-agent;x-amz-acl;x-amz-content-sha256;x-amz-date, Signature=9785e9f2a813c966536fbe02d56aec33861a8fe009be7abfc8a7af9f624e33e2', $headers['Authorization']);
					Assert::same('text/html; charset=utf-8', $headers['Content-Type']);

					return true;
				});

			$storageDriver = $this->createStorageDriver($requestSender);
			Assert::true($storageDriver->upload('/src/log/exception--2018-10-09--144c575abe.html'));
		}


		private function createStorageDriver(RemoteStorageRequestSender $requestSender): AwsS3RemoteStorageDriver
		{
			return new AwsS3RemoteStorageDriver(
				'eu-central-1',
				'my-app',
				'logs/',
				'AKIAIOSFODNN7EXAMPLE',
				' wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY',
				$requestSender
			);
		}
	}
);
