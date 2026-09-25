<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Store\Struct;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\Collection;

/**
 * @extends Collection<CartPositionStruct>
 */
#[Package('checkout')]
class CartPositionCollection extends Collection
{
    public function __construct(iterable $elements = [])
    {
        foreach ($elements as $element) {
            if (\is_array($element)) {
                $element = $this->getElementFromArray($element);
            }

            $this->add($element);
        }
    }

    protected function getExpectedClass(): ?string
    {
        return CartPositionStruct::class;
    }

    /**
     * @param array<string, mixed> $element
     */
    protected function getElementFromArray(array $element): CartPositionStruct
    {
        return CartPositionStruct::fromArray($element);
    }
}
