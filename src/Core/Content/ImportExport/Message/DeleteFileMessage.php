<?php declare(strict_types=1);

namespace Shopwell\Core\Content\ImportExport\Message;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\MessageQueue\AsyncMessageInterface;

#[Package('fundamentals@after-sales')]
class DeleteFileMessage implements AsyncMessageInterface
{
    /**
     * @var list<string>
     */
    private array $files = [];

    /**
     * @return list<string>
     */
    public function getFiles(): array
    {
        return $this->files;
    }

    /**
     * @param list<string> $files
     */
    public function setFiles(array $files): void
    {
        $this->files = $files;
    }
}
