<?php declare(strict_types=1);

namespace Shopwell\Core\System\Snippet\Service;

use League\Flysystem\FilesystemOperator;
use Shopwell\Core\Framework\Adapter\Filesystem\FilesystemFactory;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
class TranslationFilesystemFactory
{
    /**
     * @internal
     */
    public function __construct(
        private readonly FilesystemOperator $privateFilesystem,
        private readonly FilesystemFactory $filesystemFactory,
        private readonly string $projectDir,
        private readonly bool $useLocalFilesystem,
    ) {
    }

    public function create(): FilesystemOperator
    {
        if (!$this->useLocalFilesystem) {
            return $this->privateFilesystem;
        }

        return $this->filesystemFactory->privateFactory([
            'type' => 'local',
            'config' => ['root' => $this->projectDir . '/var'],
        ]);
    }
}
