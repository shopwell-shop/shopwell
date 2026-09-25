<?php declare(strict_types=1);

namespace Shopwell\Core\Content\ImportExport\Message;

use League\Flysystem\FilesystemOperator;
use League\Flysystem\UnableToDeleteFile;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[AsMessageHandler]
final readonly class DeleteFileHandler
{
    /**
     * @internal
     */
    public function __construct(private FilesystemOperator $filesystem)
    {
    }

    public function __invoke(DeleteFileMessage $message): void
    {
        foreach ($message->getFiles() as $file) {
            try {
                $this->filesystem->delete($file);
            } catch (UnableToDeleteFile) {
                // ignore file is already deleted
            }
        }
    }
}
