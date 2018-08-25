<?php declare(strict_types = 1);

namespace Mangoweb\MonologTracyHandler\RemoteStorageDrivers;

use Mangoweb\MonologTracyHandler\RemoteStorageDriver;


class NullRemoteStorageDriver implements RemoteStorageDriver
{
	public function getUrl(string $localName): ?string
	{
		return null;
	}


	public function upload(string $localPath): bool
	{
		return false;
	}
}
