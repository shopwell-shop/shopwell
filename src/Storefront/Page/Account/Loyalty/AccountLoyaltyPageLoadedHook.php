<?php declare(strict_types=1);

namespace Shopwell\Storefront\Page\Account\Loyalty;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Script\Execution\Awareness\SalesChannelContextAwareTrait;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Storefront\Page\PageLoadedHook;

/**
 * Triggered when the AccountLoyaltyPage is loaded
 *
 * @since 6.7.18.0
 *
 * @hook-use-case data_loading
 *
 * @final
 */
#[Package('checkout')]
class AccountLoyaltyPageLoadedHook extends PageLoadedHook
{
    use SalesChannelContextAwareTrait;

    final public const HOOK_NAME = 'account-loyalty-page-loaded';

    public function __construct(
        private readonly AccountLoyaltyPage $page,
        SalesChannelContext $context
    ) {
        parent::__construct($context->getContext());
        $this->salesChannelContext = $context;
    }

    public function getName(): string
    {
        return self::HOOK_NAME;
    }

    public function getPage(): AccountLoyaltyPage
    {
        return $this->page;
    }
}
