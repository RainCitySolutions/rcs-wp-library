<?php
declare(strict_types=1);
namespace RCS\Http;

use GuzzleHttp\Client;
use Psr\Container\ContainerInterface;

class GuzzleClientFactory
{
    public function __construct(
        private ?ContainerInterface $container = null
        )
    {
    }

    /**
     *
     * @param array<string, mixed> $options
     *
     * @return Client
     */
    public function create(array $options = []): Client
    {
        if ($this->container && method_exists($this->container, 'make')) {
            // Create by DI for AOP.
            $client = $this->container->make(Client::class, ['config' => $options]);
        } else {
            $client = new Client($options);
        }

        return $client;
    }
}