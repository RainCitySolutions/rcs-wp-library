<?php
declare(strict_types = 1);
namespace Fixtures;

class TestWpRestServer  // A test stub of WP_REST_Server
{
    const READABLE = 'GET';

    final public static function initMock(): void
    {
        if (!class_exists('\WP_REST_Server')) {
            \Mockery::namedMock('WP_REST_Server', TestWpRestServer::class);
        }
    }
}

