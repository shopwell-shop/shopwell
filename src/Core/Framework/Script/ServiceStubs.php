<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Script;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Script\Debugging\ScriptTraces;

/**
 * This class is intended for auto-completion in twig templates. So the developer can
 * set a doc block to get auto-completion for all services.
 *
 * @example: {# @var services \Shopwell\Core\Framework\Script\ServiceStubs #}
 *
 * @method \Shopwell\Core\Checkout\Cart\Facade\CartFacade cart()
 * @method \Shopwell\Core\Checkout\Cart\Facade\PriceFactory price()
 * @method \Shopwell\Core\Framework\DataAbstractionLayer\Facade\RepositoryFacade repository()
 * @method \Shopwell\Core\System\SystemConfig\Facade\SystemConfigFacade config()
 * @method \Shopwell\Core\Framework\DataAbstractionLayer\Facade\SalesChannelRepositoryFacade store()
 * @method \Shopwell\Core\Framework\DataAbstractionLayer\Facade\RepositoryWriterFacade writer()
 * @method \Shopwell\Core\Framework\Routing\Facade\RequestFacade request()
 * @method \Shopwell\Core\Framework\Script\Api\ScriptResponseFactoryFacade response()
 * @method \Shopwell\Core\Framework\Adapter\Cache\Script\Facade\CacheInvalidatorFacade cache()
 * @method \Shopwell\Core\Framework\Script\Api\AclFacade acl()
 */
#[Package('framework')]
final class ServiceStubs
{
    /**
     * @var array<string, array{deprecation?: string, service: object}>
     */
    private array $services = [];

    /**
     * @internal
     */
    public function __construct(private readonly string $hook)
    {
    }

    /**
     * @param array<mixed> $arguments
     *
     * @internal
     *
     * @param array<mixed> $arguments
     */
    public function __call(string $name, array $arguments): object
    {
        if (!isset($this->services[$name])) {
            throw ScriptException::serviceNotAvailableInHook($name, $this->hook);
        }

        if (isset($this->services[$name]['deprecation'])) {
            ScriptTraces::addDeprecationNotice($this->services[$name]['deprecation']);
        }

        return $this->services[$name]['service'];
    }

    /**
     * @internal
     */
    public function add(string $name, object $service, ?string $deprecationNotice = null): void
    {
        if (isset($this->services[$name])) {
            throw ScriptException::serviceAlreadyExists($name);
        }

        $this->services[$name]['service'] = $service;

        if ($deprecationNotice) {
            $this->services[$name]['deprecation'] = $deprecationNotice;
        }
    }

    /**
     * @internal
     */
    public function get(string $name): object
    {
        if (!isset($this->services[$name])) {
            throw ScriptException::serviceNotAvailableInHook($name, $this->hook);
        }

        if (isset($this->services[$name]['deprecation'])) {
            ScriptTraces::addDeprecationNotice($this->services[$name]['deprecation']);
        }

        return $this->services[$name]['service'];
    }
}
