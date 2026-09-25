<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Document;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<DocumentEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('after-sales')]
class DocumentCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'document_collection';
    }

    protected function getExpectedClass(): string
    {
        return DocumentEntity::class;
    }
}
