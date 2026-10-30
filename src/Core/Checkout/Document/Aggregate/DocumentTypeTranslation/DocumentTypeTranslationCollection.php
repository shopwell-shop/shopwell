<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Document\Aggregate\DocumentTypeTranslation;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Feature;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<DocumentTypeTranslationEntity>
 *
 * @codeCoverageIgnore
 *
 * @deprecated tag:v6.9.0 - Will be removed
 */
#[Package('after-sales')]
class DocumentTypeTranslationCollection extends EntityCollection
{
    /**
     * @param iterable<DocumentTypeTranslationEntity> $elements
     */
    public function __construct(iterable $elements = [])
    {
        Feature::throwIfActive('v6.9.0.0', Feature::deprecatedClassMessage(self::class, 'v6.9.0.0'));

        parent::__construct($elements);
    }

    public function getApiAlias(): string
    {
        return 'document_type_translation_collection';
    }

    protected function getExpectedClass(): string
    {
        return DocumentTypeTranslationEntity::class;
    }
}
