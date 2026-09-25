<?php declare(strict_types=1);

namespace Shopwell\Storefront\Page;

use Shopwell\Core\Framework\DataAbstractionLayer\Facade\RepositoryFacadeHookFactory;
use Shopwell\Core\Framework\DataAbstractionLayer\Facade\SalesChannelRepositoryFacadeHookFactory;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Routing\Facade\RequestFacadeFactory;
use Shopwell\Core\Framework\Script\Execution\Awareness\HookServiceFactory;
use Shopwell\Core\Framework\Script\Execution\Awareness\SalesChannelContextAware;
use Shopwell\Core\Framework\Script\Execution\Hook;
use Shopwell\Core\System\SystemConfig\Facade\SystemConfigFacadeHookFactory;

/**
 * @internal only rely on the concrete implementations
 */
#[Package('discovery')]
abstract class PageLoadedHook extends Hook implements SalesChannelContextAware
{
    /**
     * @return list<class-string<HookServiceFactory>>
     */
    public static function getServiceIds(): array
    {
        return [
            RepositoryFacadeHookFactory::class,
            SystemConfigFacadeHookFactory::class,
            SalesChannelRepositoryFacadeHookFactory::class,
            RequestFacadeFactory::class,
        ];
    }
}
