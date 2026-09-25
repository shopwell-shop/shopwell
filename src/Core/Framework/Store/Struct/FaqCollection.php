<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Store\Struct;

use Shopwell\Core\Framework\Deprecation\BCChange\ReturnTypeNarrowing;
use Shopwell\Core\Framework\Log\Package;

/**
 * @template-extends StoreCollection<FaqStruct>
 *
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class FaqCollection extends StoreCollection
{
    #[ReturnTypeNarrowing(version: 'v6.8.0', newType: 'string')]
    protected function getExpectedClass(): ?string
    {
        return FaqStruct::class;
    }

    protected function getElementFromArray(array $element): StoreStruct
    {
        return FaqStruct::fromArray($element);
    }
}
