<?php declare(strict_types=1);

namespace Shopwell\Core\Content\ImportExport;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Feature;
use Shopwell\Core\Framework\Log\Package;

/**
 * @deprecated tag:v6.8.0 - reason:remove-entity - Will be removed
 *
 * @extends EntityCollection<ImportExportProfileTranslationEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('fundamentals@after-sales')]
class ImportExportProfileTranslationCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0')
        );

        return ImportExportProfileTranslationEntity::class;
    }
}
