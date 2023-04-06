<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;

class ConstantContactTapTest extends TestCase
{
    public function testHasDesiredMethods()
    {
        $this->assertTrue(method_exists('ConstantContactTap', 'test'));
        $this->assertTrue(method_exists('ConstantContactTap', 'discover'));
        $this->assertTrue(method_exists('ConstantContactTap', 'tap'));
        $this->assertTrue(method_exists('ConstantContactTap', 'getTables'));
    }
}
