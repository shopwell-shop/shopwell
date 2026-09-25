<?php declare(strict_types=1);

namespace Shopwell\Core\Content\ImportExport\Service;

use Shopwell\Core\Content\ImportExport\Aggregate\ImportExportFile\ImportExportFileEntity;
use Shopwell\Core\Content\ImportExport\ImportExportProfileEntity;
use Shopwell\Core\Content\ImportExport\Processing\Writer\AbstractWriter;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\File\UploadedFile;

#[Package('fundamentals@after-sales')]
abstract class AbstractFileService
{
    abstract public function getDecorated(): AbstractFileService;

    abstract public function storeFile(
        Context $context,
        \DateTimeInterface $expireDate,
        ?string $sourcePath,
        ?string $originalFileName,
        string $activity,
        ?string $path = null
    ): ImportExportFileEntity;

    abstract public function detectType(UploadedFile $file): string;

    abstract public function getWriter(): AbstractWriter;

    abstract public function generateFilename(ImportExportProfileEntity $profile): string;

    /**
     * @param array<string, mixed> $data
     */
    abstract public function updateFile(Context $context, string $fileId, array $data): void;
}
