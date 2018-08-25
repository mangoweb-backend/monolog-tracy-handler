<?php declare(strict_types = 1);

namespace Mangoweb\MonologTracyHandler;


interface RemoteStorageDriver
{
	public function getUrl(string $localName): ?string;

	public function upload(string $localPath): bool;
}
