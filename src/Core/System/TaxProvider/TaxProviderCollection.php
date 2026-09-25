<?php declare(strict_types=1);

namespace Shopwell\Core\System\TaxProvider;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<TaxProviderEntity>
 */
#[Package('checkout')]
class TaxProviderCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'tax_provider_collection';
    }

    public function sortByPriority(): void
    {
        $this->sort(static fn (TaxProviderEntity $a, TaxProviderEntity $b) => $b->getPriority() <=> $a->getPriority());
    }

    protected function getExpectedClass(): string
    {
        return TaxProviderEntity::class;
    }
}
