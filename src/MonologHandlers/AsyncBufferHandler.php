<?php declare(strict_types = 1);

namespace Mangoweb\MangoLogger\MonologHandlers;

use Monolog\Handler\BufferHandler;


class AsyncBufferHandler extends BufferHandler
{
	public function close()
	{
		if (function_exists('fastcgi_finish_request')) {
			fastcgi_finish_request();
		}

		parent::close();
	}
}
