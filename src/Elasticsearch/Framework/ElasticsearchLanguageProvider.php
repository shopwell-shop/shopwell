<?php declare(strict_types=1);

namespace Shopwell\Elasticsearch\Framework;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\NandFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Language\LanguageCollection;
use Shopwell\Elasticsearch\Framework\Indexing\Event\ElasticsearchIndexerLanguageCriteriaEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Package('framework')]
class ElasticsearchLanguageProvider
{
    /**
     * @internal
     *
     * @param EntityRepository<LanguageCollection> $languageRepository
     */
    public function __construct(
        private readonly EntityRepository $languageRepository,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
    }

    public function getLanguages(Context $context): LanguageCollection
    {
        $criteria = new Criteria();
        $criteria->addFilter(new NandFilter([new EqualsFilter('salesChannels.id', null)]));
        $criteria->addSorting(new FieldSorting('id'));

        $this->eventDispatcher->dispatch(new ElasticsearchIndexerLanguageCriteriaEvent($criteria, $context));

        return $this->languageRepository->search($criteria, $context)->getEntities();
    }
}
