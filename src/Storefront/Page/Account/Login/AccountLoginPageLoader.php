<?php declare(strict_types=1);

namespace Shopwell\Storefront\Page\Account\Login;

use Shopwell\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopwell\Core\Framework\Adapter\Translation\AbstractTranslator;
use Shopwell\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Routing\RoutingException;
use Shopwell\Core\System\Country\CountryCollection;
use Shopwell\Core\System\Country\SalesChannel\AbstractCountryRoute;
use Shopwell\Core\System\Dictionary\Aggregate\DictionaryEntry\DictionaryEntryCollection;
use Shopwell\Core\System\Dictionary\SalesChannel\AbstractDictionaryRoute;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Storefront\Page\GenericPageLoaderInterface;
use Shopwell\Storefront\Page\MetaInformation;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Do not use direct or indirect repository calls in a PageLoader. Always use a store-api route to get or put data.
 */
#[Package('checkout')]
class AccountLoginPageLoader
{
    /**
     * @internal
     */
    public function __construct(
        private readonly GenericPageLoaderInterface $genericLoader,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly AbstractCountryRoute $countryRoute,
        private readonly AbstractDictionaryRoute $dictionaryRoute,
        private readonly AbstractTranslator $translator
    ) {
    }

    /**
     * @throws CategoryNotFoundException
     * @throws InconsistentCriteriaIdsException
     * @throws RoutingException
     */
    public function load(Request $request, SalesChannelContext $salesChannelContext): AccountLoginPage
    {
        $page = $this->genericLoader->load($request, $salesChannelContext);

        $page = AccountLoginPage::createFrom($page);
        $this->setMetaInformation($page);

        $page->setCountries($this->getCountries($salesChannelContext));

        $page->setGenders($this->getGenders($salesChannelContext));

        $this->eventDispatcher->dispatch(
            new AccountLoginPageLoadedEvent($page, $salesChannelContext, $request)
        );

        return $page;
    }

    protected function setMetaInformation(AccountLoginPage $page): void
    {
        $page->getMetaInformation()?->setRobots('noindex,follow');

        if ($page->getMetaInformation() === null) {
            $page->setMetaInformation(new MetaInformation());
        }

        $page->getMetaInformation()?->setMetaTitle(
            $this->translator->trans('account.registerMetaTitle') . ' | ' . $page->getMetaInformation()->getMetaTitle()
        );
    }

    /**
     * @throws InconsistentCriteriaIdsException
     */
    private function getGenders(SalesChannelContext $salesChannelContext): DictionaryEntryCollection
    {
        $request = new Request([], [], ['technicalName' => 'gender']);

        return $this->dictionaryRoute->load($request, $salesChannelContext, new Criteria())->getEntries();
    }

    private function getCountries(SalesChannelContext $salesChannelContext): CountryCollection
    {
        $criteria = (new Criteria())
            ->addSorting(new FieldSorting('position', FieldSorting::ASCENDING))
            ->addSorting(new FieldSorting('name', FieldSorting::ASCENDING));

        $criteria->getAssociation('states')
            ->addSorting(new FieldSorting('position', FieldSorting::ASCENDING))
            ->addSorting(new FieldSorting('name', FieldSorting::ASCENDING));

        return $this->countryRoute->load(new Request(), $criteria, $salesChannelContext)->getCountries();
    }
}
