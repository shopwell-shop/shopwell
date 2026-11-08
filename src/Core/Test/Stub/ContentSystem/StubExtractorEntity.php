<?php declare(strict_types=1);

namespace Shopwell\Core\Test\Stub\ContentSystem;

use Shopwell\Core\Framework\DataAbstractionLayer\Entity;
use Shopwell\Core\Framework\Log\Package;

/**
 * @final
 */
#[Package('framework')]
class StubExtractorEntity extends Entity
{
    public function __construct(string $id)
    {
        $this->setUniqueIdentifier($id);
    }

    public function getApiAlias(): string
    {
        return 'test_entity';
    }
}
