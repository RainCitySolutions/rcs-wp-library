<?php
declare(strict_types = 1);
namespace RCS\WP\Rest;

use JsonMapper\JsonMapperBuilder;
use JsonMapper\JsonMapperFactory;
use JsonMapper\JsonMapperInterface;
use JsonMapper\Handler\FactoryRegistry;
use JsonMapper\Handler\PropertyMapper;
use Psr\Log\LoggerInterface;
use RCS\WP\PluginInfoInterface;

abstract class RestController
{
    /** @var RestRoute[] $restRoutes The REST routes registered with WordPress to fire when the plugin loads. */
    private array $restRoutes = [];

    /** @var JsonMapperInterface The JsonMapper to use in mapping JSON to objects */
    protected JsonMapperInterface $mapper;

    /**
     *
     * @param PluginInfoInterface $pluginInfo A PluginInfo object.
     * @param int $apiVersion    The API version number for the route.
     * @param string $apiRoute The base path for the API (i.e. the part after the version number)
     * @param FactoryRegistry $factoryRegistry The object factory registry
     *      for Json objects. If not provided a default factory will be used.
     * @param LoggerInterface $logger
     */
    public function __construct(
        protected PluginInfoInterface $pluginInfo,
        readonly int $apiVersion,
        private string $apiRoute,
        readonly ?FactoryRegistry $factoryRegistry = null,
        protected ?LoggerInterface $logger = null
        )
    {
        $this->apiRoute = '/' . $this->stripRouteSlashes($apiRoute) . '/';

        $this->initializeInstance();
    }

    /**
     * Classes extending this class should call the parent and then call
     * addEndpoint() for each end point to be provided by the controller.
     */
    protected function initializeInstance(): void
    {
        // Create our own builder so we can include a PropertyMapper with additional class factories
        $builder = JsonMapperBuilder::new()
            ->withPropertyMapper(new PropertyMapper($this->factoryRegistry));

        $this->mapper = (new JsonMapperFactory($builder))->bestFit();

        add_action(
            'rest_api_init',
            function() {
                foreach ($this->restRoutes as $route) {
                    register_rest_route($route->namespace, $route->route, $route->args);
                }
            }
        );
    }

    /**
     * Add a new REST endpoint to the collection to be registered with WordPress.
     *
     * The 'methods' element of $args defaults to 'GET'.<br>
     * The 'callback' element of $args should not be provied. It will be overridden.
     *
     * @param string                $route      The route URI for the portion beyond the apiRoute provided during construction.
     * @param string|object|null    $component  A reference to a class for static methods, an object for instance methods or null.
     * @param string|callable       $callback   The name of a method on the class or object on the $component, the name of a function or a function.
     * @param string                $method     The HTTP method
     * @param array<string, mixed>  $args       The arguments to be provided to register_rest_route.
     */
    protected function addEndpoint(
        string $route,
        string|object|null $component,
        string|callable $callback,
        string $method,
        array $args = []
        ): void
    {
        $args['methods'] = $method;
        $args['callback'] = $this->getCallback($component, $callback);

        if (!isset($args['permission_callback'])) {
            $args['permission_callback'] = '__return_true';
        }

        $restRoute = new RestRoute(
            $this->pluginInfo->getSlug() . '/v' . $this->apiVersion,
            $this->apiRoute . $this->stripRouteSlashes($route),
            $args
        );

        $this->restRoutes[] = $restRoute;
    }

    /**
     * Create a callable reference from $component and $callback.
     *
     * @param string|object|null    $component  A reference to a class for static methods, an object for instance methods or null.
     * @param string|callable       $callback   The name of a method on the class or object on the $component, the name of a function or a function.
     *
     * @throws \InvalidArgumentException    Thrown if the arguments passed cannot be combined into a callable method.
     *
     * @return callable A reference to a callable method.
     */
    private function getCallback(string|object|null $component, string|callable $callback): callable
    {
        // If the $callback parameter is callable, use that ignoring the $component argument
        if (is_callable($callback)) {
            $callbackFunc = $callback;
        }
        else {
            if (is_object($component)) {
                $callbackFunc = array($component, $callback);
            }
            elseif (is_string($component)) {
                $callbackFunc = array($component, $callback);
            }
            else {
                $callbackFunc = $callback;
            }

            if (!is_callable($callbackFunc)) {
                throw new \InvalidArgumentException("Invalid callback defined.");
            }
        }

        return $callbackFunc;
    }

    /**
     * Removes the leading and/or trailing slashes from the route.
     *
     * @param string $route A REST route URL
     *
     * @return string The route without a leading and/or trailing slash
     */
    private function stripRouteSlashes(string $route): string
    {
        $matches = [];
        $route = trim($route);

        if (preg_match('#^/*(.*?)/*$#', $route, $matches)) {
            $route = $matches[1];
        }

        return $route;
    }

}

class RestRoute
{
    public string $namespace;
    public string $route;
    /** @var array<string, mixed> */
    public array $args;

    /**
     *
     * @param string $namespace
     * @param string $route
     * @param array<string, mixed> $args
     */
    public function __construct(string $namespace, string $route, array $args)
    {
        $this->namespace = $namespace;
        $this->route = $route;
        $this->args = $args;
    }
}