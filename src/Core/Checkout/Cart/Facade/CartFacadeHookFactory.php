<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Cart\Facade;

use Shopwell\Core\Checkout\Cart\CartException;
use Shopwell\Core\Checkout\Cart\Hook\CartAware;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Script\Execution\Awareness\HookServiceFactory;
use Shopwell\Core\Framework\Script\Execution\Hook;
use Shopwell\Core\Framework\Script\Execution\Script;

/**
 * @internal
 */
#[Package('checkout')]
class CartFacadeHookFactory extends HookServiceFactory
{
    public function __construct(
        private readonly CartFacadeHelper $helper,
        private readonly ScriptPriceStubs $priceStubs
    ) {
    }

    public function factory(Hook $hook, Script $script): CartFacade
    {
        if (!$hook instanceof CartAware) {
            throw CartException::hookInjectionException($hook, self::class, CartAware::class);
        }

        return new CartFacade($this->helper, $this->priceStubs, $hook->getCart(), $hook->getSalesChannelContext());
    }

    /**
     * @param CartFacade $service
     */
    public function after(object $service, Hook $hook, Script $script): void
    {
        $service->calculate();
    }

    public function getName(): string
    {
        return 'cart';
    }
}
