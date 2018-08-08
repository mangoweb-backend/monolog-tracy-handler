<?php declare(strict_types = 1);

namespace Mangoweb\MonologTracyHandler;


interface RemoteStorageDriver
{
	public function getUrl(string $localPath): ?string;

	public function upload(string $localPath): bool;
}
