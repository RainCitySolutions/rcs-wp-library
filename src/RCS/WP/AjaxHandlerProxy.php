<?php
declare(strict_types = 1);
namespace RCS\WP;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\LoggerInterface;
use RCS\WP\Ajax\AjaxHandlerImplInf;

/**
 * This class acts as a proxy for classes implementing AJAX handlers,
 * specifically those implementing the AjaxHandlerImplInf. The class will
 * only instantiate the class for a handler implemention when there is a
 * request to handle an AJAX request for that handlere. This is done with the
 * aid of a Dependency Injection framework such as PHP-DI.
 */
class AjaxHandlerProxy
{
    /**
     * Map of actions to the class that handles the action
     *
     * @var array<string, string>
     */
    private array $handlerMap = [];

    public function __construct(
        private ContainerInterface $diContainer,
        private LoggerInterface $logger
        )
    {}

    public function handleAjaxRequest(): void
    {
        $matches = [];

        if (preg_match('/^(wp_ajax_nopriv_|wp_ajax_)(.*)$/', current_action(), $matches)) {
            $isPublic = 'wp_ajax_nopriv_' == $matches[1];
            $action = $matches[2];

            // Verify the action is in our map
            if (array_key_exists($action, $this->handlerMap)) {
                try {
                    // Get an instance of the handler class from the DI framework
                    /** @var AjaxHandlerImplInf */
                    $obj = $this->diContainer->get($this->handlerMap[$action]);

                    if ($isPublic) {
                        $obj->handlePublicAjaxRequest($action);
                    } else {
                        $obj->handlePrivateAjaxRequest($action);
                    }
                }
                catch (NotFoundExceptionInterface | ContainerExceptionInterface $e) {
                    $this->logger->error(
                        'Unable to retrieve class instance for AJAX handler {hdlr}: {err}',
                        [
                            'hdlr' => $action,
                            'err' => $e->getMessage()
                        ]
                        );
                }
            }
        }

        wp_die();
    }

    /**
     * Adds an AJAX handler class to the proxy.
     *
     * @param string $ajaxHandlerClass
     */
    public function addHandler(string $ajaxHandlerClass): void
    {
        // Ensure the class implements the ShortcodeImplInf interface
        assert(
            is_a($ajaxHandlerClass, AjaxHandlerImplInf::class, true),
            $ajaxHandlerClass. ' must implement ' . AjaxHandlerImplInf::class
            );

        $actions = $ajaxHandlerClass::getAjaxActions();

        foreach ($actions as $action) {
            if (!array_key_exists($action, $this->handlerMap))
            {
                // Add the mapping
                $this->handlerMap[$action] = $ajaxHandlerClass;

                add_action('wp_ajax_nopriv_'.$action, [$this, 'handleAjaxRequest']);
                add_action('wp_ajax_'.$action, [$this, 'handleAjaxRequest']);
            }
        }
    }

    /**
     * Add a set of AJAX handler classes to the proxy.
     *
     * @param list<string> $handlers
     */
    public function addHandlers(array $handlers): void
    {
        foreach($handlers as $class) {
            $this->addHandler($class);
        }
    }
}
