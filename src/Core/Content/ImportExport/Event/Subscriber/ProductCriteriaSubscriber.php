<?php declare(strict_types=1);

namespace Shopwell\Core\Content\ImportExport\Event\Subscriber;

use Shopwell\Core\Content\ImportExport\Event\EnrichExportCriteriaEvent;
use Shopwell\Core\Content\ImportExport\ImportExportProfileEntity;
use Shopwell\Core\Content\ImportExport\Struct\Config;
use Shopwell\Core\Content\Product\ProductDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
class ProductCriteriaSubscriber implements EventSubscriberInterface
{
    /**
     * @return array<string, string|array{0: string, 1: int}|list<array{0: string, 1?: int}>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            EnrichExportCriteriaEvent::class => 'enrich',
        ];
    }

    public function enrich(EnrichExportCriteriaEvent $event): void
    {
        /** @var ImportExportProfileEntity $profile */
        $profile = $event->getLogEntity()->getProfile();
        if ($profile->getSourceEntity() !== ProductDefinition::ENTITY_NAME) {
            return;
        }

        $criteria = $event->getCriteria();
        $criteria->resetSorting();

        $criteria->addSorting(new FieldSorting('autoIncrement'));

        $config = Config::fromLog($event->getLogEntity());

        if ($config->get('includeVariants') !== true) {
            $criteria->addFilter(new EqualsFilter('parentId', null));
        }

        $this->excludeUnmappedDescription($criteria, $config);
    }

    /**
     * Skips loading the heavy `description` column when the export profile never maps it. The column
     * is exported through the `translations` association, so it is dropped there too (only when the
     * association is already loaded — we never add it just to reduce it). Left untouched if a field
     * selection is already set.
     */
    private function excludeUnmappedDescription(Criteria $criteria, Config $config): void
    {
        foreach ($config->getMapping() as $mapping) {
            if (\in_array('description', explode('.', $mapping->getKey()), true)) {
                return;
            }
        }

        if ($criteria->getFields() === [] && $criteria->getExcludedFields() === []) {
            $criteria->excludeFields(['description']);
        }

        if (!$criteria->hasAssociation('translations')) {
            return;
        }

        $translations = $criteria->getAssociation('translations');
        if ($translations->getFields() === [] && $translations->getExcludedFields() === []) {
            $translations->excludeFields(['description']);
        }
    }
}
