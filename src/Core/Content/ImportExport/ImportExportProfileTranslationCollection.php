<?php declare(strict_types=1);

namespace Shopwell\Core\Content\ImportExport;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Feature;
use Shopwell\Core\Framework\Log\Package;

/**
 * @deprecated tag:v6.8.0 - Will be removed
 *
 * @extends EntityCollection<ImportExportProfileTranslationEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('fundamentals@after-sales')]
class ImportExportProfileTranslationCollection extends EntityCollection
{
    /**
     * @param iterable<ImportExportProfileTranslationEntity> $elements
     */
    public function __construct(iterable $elements = [])
    {
        Feature::throwIfActive('v6.8.0.0', Feature::deprecatedClassMessage(self::class, 'v6.8.0.0'));

        parent::__construct($elements);
    }

    protected function getExpectedClass(): string
    {
        return ImportExportProfileTranslationEntity::class;
    }
}
