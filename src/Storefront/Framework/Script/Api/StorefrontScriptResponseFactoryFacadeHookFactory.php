<?php declare(strict_types=1);

namespace Shopwell\Storefront\Framework\Script\Api;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Script\Api\ScriptResponseFactoryFacade;
use Shopwell\Core\Framework\Script\Api\ScriptResponseFactoryFacadeHookFactory;
use Shopwell\Core\Framework\Script\Execution\Hook;
use Shopwell\Core\Framework\Script\Execution\Script;
use Shopwell\Storefront\Controller\ScriptController;
use Symfony\Component\Routing\RouterInterface;

/**
 * Decorates the core `response` hook-service factory so that, when the Storefront bundle is installed,
 * scripts receive a {@see StorefrontScriptResponseFactoryFacade} that can render Twig views.
 *
 * @internal
 */
#[Package('discovery')]
class StorefrontScriptResponseFactoryFacadeHookFactory extends ScriptResponseFactoryFacadeHookFactory
{
    public function __construct(
        private readonly RouterInterface $router,
        private readonly ScriptController $scriptController,
    ) {
        parent::__construct($router);
    }

    public function factory(Hook $hook, Script $script): ScriptResponseFactoryFacade
    {
        \assert($hook instanceof StorefrontHook);

        return new StorefrontScriptResponseFactoryFacade(
            $this->router,
            $this->scriptController,
            $hook->getSalesChannelContext()
        );
    }
}
