<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Promotion\Aggregate\PromotionIndividualCode;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<PromotionIndividualCodeEntity>
 *
 * @codeCoverageIgnore
 *
 * @see \Shopwell\Tests\Integration\Core\Content\ImportExport\ImportExportTest
 */
#[Package('checkout')]
class PromotionIndividualCodeCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'promotion_individual_code_collection';
    }

    /**
     * @return array<string>
     */
    public function getCodeArray(): array
    {
        $codes = [];
        foreach ($this->getIterator() as $codeEntity) {
            $codes[] = $codeEntity->getCode();
        }

        return $codes;
    }

    protected function getExpectedClass(): string
    {
        return PromotionIndividualCodeEntity::class;
    }
}
