<?php declare(strict_types=1);

namespace Shopwell\Storefront\Page\Account\Wallet;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Script\Execution\Awareness\SalesChannelContextAwareTrait;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Storefront\Page\PageLoadedHook;

/**
 * Triggered when the AccountWalletPage is loaded
 *
 * @hook-use-case data_loading
 *
 * @final
 */
#[Package('checkout')]
class AccountWalletPageLoadedHook extends PageLoadedHook
{
    use SalesChannelContextAwareTrait;

    final public const HOOK_NAME = 'account-wallet-page-loaded';

    public function __construct(
        private readonly AccountWalletPage $page,
        SalesChannelContext $context
    ) {
        parent::__construct($context->getContext());
        $this->salesChannelContext = $context;
    }

    public function getName(): string
    {
        return self::HOOK_NAME;
    }

    public function getPage(): AccountWalletPage
    {
        return $this->page;
    }
}
