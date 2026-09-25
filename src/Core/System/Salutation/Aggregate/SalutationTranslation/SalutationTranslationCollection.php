<?php declare(strict_types=1);

namespace Shopwell\Core\System\Salutation\Aggregate\SalutationTranslation;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<SalutationTranslationEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class SalutationTranslationCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'salutation_translation_collection';
    }

    protected function getExpectedClass(): string
    {
        return SalutationTranslationEntity::class;
    }
}
