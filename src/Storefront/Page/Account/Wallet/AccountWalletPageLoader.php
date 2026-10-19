<?php declare(strict_types=1);

namespace Shopwell\Storefront\Page\Account\Wallet;

use Shopwell\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Checkout\Wallet\SalesChannel\WalletRoute;
use Shopwell\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopwell\Core\Framework\Adapter\Translation\AbstractTranslator;
use Shopwell\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Routing\RoutingException;
use Shopwell\Core\System\Currency\SalesChannel\AbstractCurrencyRoute;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Storefront\Page\GenericPageLoaderInterface;
use Shopwell\Storefront\Page\MetaInformation;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Do not use direct or indirect repository calls in a PageLoader. Always use a store-api route to get or put data.
 */
#[Package('checkout')]
class AccountWalletPageLoader
{
    private const DEFAULT_LIMIT = 25;

    private const MAX_LIMIT = 100;

    /**
     * @internal
     */
    public function __construct(
        private readonly GenericPageLoaderInterface $genericLoader,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly WalletRoute $walletRoute,
        private readonly AbstractCurrencyRoute $currencyRoute,
        private readonly AbstractTranslator $translator
    ) {
    }

    /**
     * @throws CategoryNotFoundException
     * @throws CustomerNotLoggedInException
     * @throws InconsistentCriteriaIdsException
     * @throws RoutingException
     */
    public function load(Request $request, SalesChannelContext $salesChannelContext, CustomerEntity $customer): AccountWalletPage
    {
        $page = $this->genericLoader->load($request, $salesChannelContext);

        $page = AccountWalletPage::createFrom($page);
        $this->setMetaInformation($page);

        $wallet = $this->walletRoute->load($request, $salesChannelContext, $customer)->getWallet();

        $page->setWallet($wallet);
        $page->setCurrentPage(max($request->query->getInt('page', 1), 1));
        $page->setLimit(min(max($request->query->getInt('limit', self::DEFAULT_LIMIT), 1), self::MAX_LIMIT));
        $page->setCurrencyIsoCodes($this->loadCurrencyIsoCodes($request, $salesChannelContext));

        $this->eventDispatcher->dispatch(
            new AccountWalletPageLoadedEvent($page, $salesChannelContext, $request)
        );

        return $page;
    }

    protected function setMetaInformation(AccountWalletPage $page): void
    {
        if ($page->getMetaInformation()) {
            $page->getMetaInformation()->setRobots('noindex,follow');
        }

        if ($page->getMetaInformation() === null) {
            $page->setMetaInformation(new MetaInformation());
        }

        $page->getMetaInformation()?->setMetaTitle(
            $this->translator->trans('account.walletMetaTitle') . ' | ' . $page->getMetaInformation()->getMetaTitle()
        );
    }

    /**
     * @return array<string, string>
     */
    private function loadCurrencyIsoCodes(Request $request, SalesChannelContext $salesChannelContext): array
    {
        $currencies = $this->currencyRoute
            ->load($request, $salesChannelContext, new Criteria())
            ->getCurrencies();

        $isoCodes = [];

        foreach ($currencies as $currency) {
            $isoCodes[$currency->getId()] = $currency->getIsoCode();
        }

        return $isoCodes;
    }
}
