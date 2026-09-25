<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Product\Events;

use Shopwell\Core\Framework\Log\Package;

#[Package('inventory')]
interface ProductChangedEventInterface
{
    /**
     * @return list<string>
     */
    public function getIds(): array;
}
