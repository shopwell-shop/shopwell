<?php declare(strict_types=1);

namespace Shopwell\Core\Content\ImportExport\Aggregate\ImportExportFile;

use Shopwell\Core\Framework\Log\Package;

#[Package('fundamentals@after-sales')]
class ImportExportFileEvents
{
    final public const IMPORT_EXPORT_FILE_WRITTEN_EVENT = 'import_export_file.written';

    final public const IMPORT_EXPORT_FILE_DELETED_EVENT = 'import_export_file.deleted';

    final public const IMPORT_EXPORT_FILE_LOADED_EVENT = 'import_export_file.loaded';

    final public const IMPORT_EXPORT_FILE_SEARCH_RESULT_LOADED_EVENT = 'import_export_file.search.result.loaded';

    final public const IMPORT_EXPORT_FILE_AGGREGATION_LOADED_EVENT = 'import_export_file.aggregation.result.loaded';

    final public const IMPORT_EXPORT_FILE_ID_SEARCH_RESULT_LOADED_EVENT = 'import_export_file.id.search.result.loaded';
}
