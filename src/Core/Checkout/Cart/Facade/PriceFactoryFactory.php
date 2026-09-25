<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Cart\Facade;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Script\Execution\Awareness\HookServiceFactory;
use Shopwell\Core\Framework\Script\Execution\Hook;
use Shopwell\Core\Framework\Script\Execution\Script;

/**
 * @internal
 */
#[Package('checkout')]
class PriceFactoryFactory extends HookServiceFactory
{
    public function __construct(private readonly ScriptPriceStubs $stubs)
    {
    }

    public function factory(Hook $hook, Script $script): PriceFactory
    {
        return new PriceFactory($this->stubs);
    }

    public function getName(): string
    {
        return 'price';
    }
}
