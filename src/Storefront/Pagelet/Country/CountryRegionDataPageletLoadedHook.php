<?php declare(strict_types=1);

namespace Shopwell\Storefront\Pagelet\Country;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Script\Execution\Awareness\SalesChannelContextAwareTrait;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Storefront\Page\PageLoadedHook;

/**
 * Triggered when the CountryRegionDataPagelet is loaded
 *
 * @hook-use-case data_loading
 *
 * @final
 */
#[Package('discovery')]
class CountryRegionDataPageletLoadedHook extends PageLoadedHook
{
    use SalesChannelContextAwareTrait;

    final public const HOOK_NAME = 'country-region-data-pagelet-loaded';

    public function __construct(
        private readonly CountryRegionDataPagelet $pagelet,
        SalesChannelContext $context
    ) {
        parent::__construct($context->getContext());
        $this->salesChannelContext = $context;
    }

    public function getName(): string
    {
        return self::HOOK_NAME;
    }

    public function getPage(): CountryRegionDataPagelet
    {
        return $this->pagelet;
    }
}
