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


		public function testGetUrlWithHostOverride(): void
		{
			$requestSender = Mockery::mock(RemoteStorageRequestSender::class);
			$storageDriver = $this->createStorageDriverWithHostOverride($requestSender, 'custom.example.com');

			Assert::same(
				'https://custom.example.com/my-app/logs/a5ef95ed6b795b3dfed85238d3003cae.html',
				$storageDriver->getUrl('exception--2018-10-09--144c575abe.html')
			);
		}


		public function testUploadWithHostOverride(): void
		{
			$requestSender = Mockery::mock(RemoteStorageRequestSender::class);
			$requestSender->expects('sendRequest')
				->andReturnUsing(function (string $method, string $url, array $headers, string $bodyFilePath): bool {
					Assert::same('PUT', $method);
					Assert::same('https://custom.example.com/my-app/logs/a5ef95ed6b795b3dfed85238d3003cae.html', $url);
					Assert::same('/src/log/exception--2018-10-09--144c575abe.html', $bodyFilePath);

					Assert::count(7, $headers);
					Assert::same('custom.example.com', $headers['Host']);
					Assert::same('MangoLogger', $headers['User-Agent']);
					Assert::same('public-read', $headers['X-Amz-ACL']);
					Assert::same('UNSIGNED-PAYLOAD', $headers['X-Amz-Content-Sha256']);
					Assert::same('20181009T213200Z', $headers['X-Amz-Date']);
					// Verify that authorization header contains the correct credential and is properly formatted
					Assert::match(
						'AWS4-HMAC-SHA256 Credential=AKIAIOSFODNN7EXAMPLE/20181009/eu-central-1/s3/aws4_request, SignedHeaders=host;user-agent;x-amz-acl;x-amz-content-sha256;x-amz-date, Signature=%h%',
						$headers['Authorization']
					);
					// Ensure the signature is different from the default host signature (due to different host)
					Assert::notSame(
						'9785e9f2a813c966536fbe02d56aec33861a8fe009be7abfc8a7af9f624e33e2',
						substr($headers['Authorization'], -64) // Last 64 chars are the signature
					);
					Assert::same('text/html; charset=utf-8', $headers['Content-Type']);

					return true;
				});

			$storageDriver = $this->createStorageDriverWithHostOverride($requestSender, 'custom.example.com');
			Assert::true($storageDriver->upload('/src/log/exception--2018-10-09--144c575abe.html'));
		}


		public function testHostOverrideWithDifferentProtocols(): void
		{
			$requestSender = Mockery::mock(RemoteStorageRequestSender::class);
			
			// Test with just hostname
			$storageDriver1 = $this->createStorageDriverWithHostOverride($requestSender, 'minio.local');
			Assert::same(
				'https://minio.local/my-app/logs/a5ef95ed6b795b3dfed85238d3003cae.html',
				$storageDriver1->getUrl('exception--2018-10-09--144c575abe.html')
			);

			// Test with hostname and port
			$storageDriver2 = $this->createStorageDriverWithHostOverride($requestSender, 'localhost:9000');
			Assert::same(
				'https://localhost:9000/my-app/logs/a5ef95ed6b795b3dfed85238d3003cae.html',
				$storageDriver2->getUrl('exception--2018-10-09--144c575abe.html')
			);
		}


		public function testHostOverrideNullUsesDefaultHost(): void
		{
			$requestSender = Mockery::mock(RemoteStorageRequestSender::class);
			$storageDriver = $this->createStorageDriverWithHostOverride($requestSender, null);

			// When hostOverride is null, it should behave exactly like the default
			Assert::same(
				'https://s3.eu-central-1.amazonaws.com/my-app/logs/a5ef95ed6b795b3dfed85238d3003cae.html',
				$storageDriver->getUrl('exception--2018-10-09--144c575abe.html')
			);
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


		private function createStorageDriverWithHostOverride(RemoteStorageRequestSender $requestSender, ?string $hostOverride): AwsS3RemoteStorageDriver
		{
			return new AwsS3RemoteStorageDriver(
				'eu-central-1',
				'my-app',
				'logs/',
				'AKIAIOSFODNN7EXAMPLE',
				' wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY',
				$requestSender,
				$hostOverride
			);
		}
	}
);
