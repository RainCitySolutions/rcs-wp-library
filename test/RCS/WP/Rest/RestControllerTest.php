<?php
declare(strict_types=1);
namespace RCS\WP\Rest;

use Brain\Monkey;
use Brain\Monkey\Functions;
use JsonMapper\Handler\FactoryRegistry;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use RCS\WP\PluginInfoInterface;

#[CoversClass(RestController::class)]
#[CoversClass(RestRoute::class)]
final class RestControllerTest extends TestCase
{
    protected function setUp(): void
    {
        Monkey\setUp();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
    }

    /**
     * Create a testable concrete subclass of RestController.
     */
    private function createController(callable &$registeredCallback = null): TestRestController
    {
        $pluginInfo = $this->createMock(PluginInfoInterface::class);
        $pluginInfo->method('getSlug')->willReturn('my-plugin');

        $logger = $this->createMock(LoggerInterface::class);
        $registry = $this->createMock(FactoryRegistry::class);

        // Capture but do NOT run callback now.
        Functions\expect('add_action')
            ->once()
            ->andReturnUsing(function (string $hook, callable $callback) use (&$registeredCallback): void {
                if ($hook === 'rest_api_init') {
                    $registeredCallback = $callback; // Save for later manual trigger
                }
            });

        return new TestRestController($pluginInfo, 1, '/api', $registry, $logger);
//         return new class($pluginInfo, 1, '/api', $registry, $logger) extends RestController {
//             public function exposeAddEndpoint(
//                 string $route,
//                 string|object|null $component,
//                 string|callable $callback,
//                 string $method,
//                 array $args = []
//             ): void {
//                 self::addEndpoint($route, $component, $callback, $method, $args);
//             }

//             public function stripPublic(string $route): string
//             {
//                 $r = new \ReflectionClass($this);
//                 $m = $r->getMethod('stripRouteSlashes');
//                 $m->setAccessible(true);
//                 return $m->invoke($this, $route);
//             }

//             public function callbackPublic(string|object|null $c, string|callable $cb): callable
//             {
//                 $r = new \ReflectionClass($this);
//                 $m = $r->getMethod('getCallback');
//                 $m->setAccessible(true);
//                 return $m->invoke($this, $c, $cb);
//             }
//         };
    }

    #[Test]
    public function it_delays_callback_until_rest_api_init_is_triggered(): void
    {
        $registered = null;

        $controller = $this->createController($registered);

        $endpointCallback = '__return_true';
        // Add endpoint (but rest_api_init callback not yet run)

        $controller->exposeAddEndpoint('hello', null, $endpointCallback, 'GET');

        // Now, simulate WordPress firing rest_api_init
        Functions\expect('register_rest_route')
            ->once()
            ->with('my-plugin/v1', '/api/hello', \Mockery::on(fn($a) => $a['methods'] === 'GET' && $a['permission_callback'] === '__return_true'));

        // Trigger manually saved callback
        self::assertIsCallable($registered, 'Callback from add_action was not captured.');
        $registered(); // simulate do_action('rest_api_init')
    }

    #[Test]
    public function it_builds_callable_from_object_and_method(): void
    {
        $controller = $this->createController($dummy);

        $obj = new class {
            public function greet(): string { return 'hi'; }
        };

        $cb = $controller->callbackPublic($obj, 'greet');
        self::assertSame('hi', $cb());
    }

    #[Test]
    public function it_strips_leading_and_trailing_slashes(): void
    {
        $controller = $this->createController($dummy);
        self::assertSame('my/route', $controller->stripPublic('/my/route/'));
    }

    #[Test]
    public function it_throws_for_invalid_callback(): void
    {
        $controller = $this->createController($dummy);
        $this->expectException(\InvalidArgumentException::class);
        $controller->callbackPublic(null, 'nonexistent_function_123');
    }
}

class TestRestController
    extends RestController
{
    /**
     *
     * @param string $route
     * @param string|object|null $component
     * @param string|callable $callback
     * @param string $method
     * @param array<string, mixed> $args
     */
    public function exposeAddEndpoint(
        string $route,
        string|object|null $component,
        string|callable $callback,
        string $method,
        array $args = []
        ): void {
            self::addEndpoint($route, $component, $callback, $method, $args);
    }

    public function stripPublic(string $route): string
    {
        $r = new \ReflectionClass($this);
        $m = $r->getMethod('stripRouteSlashes');
        $m->setAccessible(true);
        return $m->invoke($this, $route);
    }

    public function callbackPublic(string|object|null $c, string|callable $cb): callable
    {
        $r = new \ReflectionClass($this);
        $m = $r->getMethod('getCallback');
        $m->setAccessible(true);
        return $m->invoke($this, $c, $cb);
    }
}
