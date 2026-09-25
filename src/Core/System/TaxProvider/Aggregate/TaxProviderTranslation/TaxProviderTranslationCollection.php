<?php declare(strict_types=1);

namespace Shopwell\Core\System\TaxProvider\Aggregate\TaxProviderTranslation;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<TaxProviderTranslationEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class TaxProviderTranslationCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'tax_provider_translation_collection';
    }

    protected function getExpectedClass(): string
    {
        return TaxProviderTranslationEntity::class;
    }
}
