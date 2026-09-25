<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Script\Api;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Script\Execution\Awareness\HookServiceFactory;
use Shopwell\Core\Framework\Script\Execution\Awareness\SalesChannelContextAware;
use Shopwell\Core\Framework\Script\Execution\Hook;
use Shopwell\Core\Framework\Script\Execution\Script;
use Shopwell\Storefront\Controller\ScriptController;
use Symfony\Component\Routing\RouterInterface;

/**
 * @internal
 */
#[Package('framework')]
class ScriptResponseFactoryFacadeHookFactory extends HookServiceFactory
{
    public function __construct(
        private readonly RouterInterface $router,
        /**
         * @deprecated tag:v6.8.0 - only needed for the deprecated render() BC path.
         *
         * @phpstan-ignore phpat.restrictNamespacesInCore (Storefront dependency is nullable. Don't do that! Will be removed with v6.8.0 when render() is removed from the core response facade)
         */
        private readonly ?ScriptController $scriptController = null,
    ) {
    }

    public function factory(Hook $hook, Script $script): ScriptResponseFactoryFacade
    {
        // @deprecated tag:v6.8.0 - only needed for the deprecated render() BC path.
        $salesChannelContext = $hook instanceof SalesChannelContextAware ? $hook->getSalesChannelContext() : null;

        return new ScriptResponseFactoryFacade(
            $this->router,
            $this->scriptController,
            $salesChannelContext
        );
    }

    public function getName(): string
    {
        return 'response';
    }
}
