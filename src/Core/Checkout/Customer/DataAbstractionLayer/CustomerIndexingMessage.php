<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Customer\DataAbstractionLayer;

use Shopwell\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;
use Shopwell\Core\Framework\Log\Package;

#[Package('checkout')]
class CustomerIndexingMessage extends EntityIndexingMessage
{
    /**
     * @var string[]
     */
    private array $ids = [];

    /**
     * @return string[]
     */
    public function getIds(): array
    {
        return $this->ids;
    }

    /**
     * @param array<string> $ids
     */
    public function setIds(array $ids): void
    {
        $this->ids = $ids;
    }
}
