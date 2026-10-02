<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\DocumentV2\Aggregate\DocumentBaseConfig;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Deprecation\BCChange\ClassMoved;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<DocumentBaseConfigEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('after-sales')]
#[ClassMoved(version: 'v6.9.0', previousClassName: 'Shopwell\Core\Checkout\Document\Aggregate\DocumentBaseConfig\DocumentBaseConfigCollection')]
class DocumentBaseConfigCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'document_base_collection';
    }

    protected function getExpectedClass(): string
    {
        return DocumentBaseConfigEntity::class;
    }
}
