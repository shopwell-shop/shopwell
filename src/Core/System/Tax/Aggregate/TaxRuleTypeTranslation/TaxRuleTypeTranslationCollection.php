<?php declare(strict_types=1);

namespace Shopwell\Core\System\Tax\Aggregate\TaxRuleTypeTranslation;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<TaxRuleTypeTranslationEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class TaxRuleTypeTranslationCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'tax_rule_type_translation_collection';
    }

    protected function getExpectedClass(): string
    {
        return TaxRuleTypeTranslationEntity::class;
    }
}
