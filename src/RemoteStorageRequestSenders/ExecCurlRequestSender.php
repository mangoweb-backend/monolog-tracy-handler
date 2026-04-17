<?php declare(strict_types = 1);

namespace Mangoweb\MonologTracyHandler\RemoteStorageRequestSenders;

use Mangoweb\MonologTracyHandler\RemoteStorageRequestSender;


class ExecCurlRequestSender implements RemoteStorageRequestSender
{
	private string $curlBinary;

	private bool $async;


	public function __construct(string $curlBinary = 'curl', ?bool $async = null)
	{
		$this->curlBinary = $curlBinary;
		$this->async = $async ?? (PHP_SAPI !== 'cli');
	}


	/**
	 * @param array<string, string> $headers
	 */
	public function sendRequest(string $method, string $url, array $headers, string $bodyFilePath): bool
	{
		$baseArgs = [$this->curlBinary, '--request', $method, '--url', $url];

		foreach ($headers as $headerName => $headerValue) {
			$baseArgs[] = '--header';
			$baseArgs[] = "$headerName: $headerValue";
		}

		if (!$this->async) {
			return $this->execSync([...$baseArgs, '--upload-file', $bodyFilePath]);
		}

		// In async mode the backgrounded curl process keeps reading $bodyFilePath
		// after sendRequest() returns, so the caller can race us by overwriting
		// or deleting the file. Upload from a temp copy instead and remove it
		// from the background shell once curl is done.
		$tempPath = $this->createUploadCopy($bodyFilePath);

		if ($tempPath === null) {
			return $this->execSync([...$baseArgs, '--upload-file', $bodyFilePath]);
		}

		return $this->execAsync([...$baseArgs, '--upload-file', $tempPath], $tempPath);
	}


	/**
	 * @param list<string> $args
	 */
	private function execSync(array $args): bool
	{
		$command = implode(' ', array_map('escapeshellarg', $args)) . ' >/dev/null 2>&1';
		exec($command, $output, $exitCode);

		return $exitCode === 0;
	}


	/**
	 * @param list<string> $args
	 */
	private function execAsync(array $args, string $tempPath): bool
	{
		$curl = implode(' ', array_map('escapeshellarg', $args));
		$cleanup = 'rm -f ' . escapeshellarg($tempPath);
		$command = "($curl >/dev/null 2>&1; $cleanup) >/dev/null 2>&1 &";
		exec($command, $output, $exitCode);

		return $exitCode === 0;
	}


	private function createUploadCopy(string $bodyFilePath): ?string
	{
		$tempPath = @tempnam(sys_get_temp_dir(), 'tracy-upload-');

		if ($tempPath === false) {
			return null;
		}

		if (!@copy($bodyFilePath, $tempPath)) {
			@unlink($tempPath);

			return null;
		}

		return $tempPath;
	}
}
