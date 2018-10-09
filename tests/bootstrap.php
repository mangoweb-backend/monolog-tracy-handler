<?php declare(strict_types = 1);

require __DIR__ . '/../vendor/autoload.php';

return function (Tester\TestCase $testCase): void {
	Tester\Environment::setup();
	$testCase->run();
};
