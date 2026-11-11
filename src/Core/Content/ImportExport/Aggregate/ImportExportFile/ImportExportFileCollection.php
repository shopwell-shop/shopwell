<?php declare(strict_types=1);

namespace Shopwell\Core\Content\ImportExport\Aggregate\ImportExportFile;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<ImportExportFileEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('fundamentals@after-sales')]
class ImportExportFileCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'import_export_file_collection';
    }

    protected function getExpectedClass(): string
    {
        return ImportExportFileEntity::class;
    }
}
