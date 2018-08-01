<?php declare(strict_types = 1);

namespace Mangoweb\MangoLogger\RemoteStorageDrivers;


use Mangoweb\MangoLogger\RemoteStorageDriver;

class NullRemoteStorageDriver implements RemoteStorageDriver
{
	public function getUrl(string $localPath): ?string
	{
		return null;
	}


	public function upload(string $localPath): bool
	{
		return false;
	}
}
