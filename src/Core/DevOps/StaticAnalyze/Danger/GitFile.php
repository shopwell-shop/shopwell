<?php declare(strict_types=1);

namespace Shopwell\Core\DevOps\StaticAnalyze\Danger;

use Danger\Exception\CouldNotGetFileContentException;
use Danger\Struct\File;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\Process\Process;

/**
 * @internal
 */
#[Package('framework')]
class GitFile extends File
{
    private ?string $content = null;

    public function __construct(
        private readonly string $repository,
        private readonly string $head,
        string $name,
        string $status = File::STATUS_MODIFIED,
        int $additions = 0,
        int $deletions = 0,
        string $patch = '',
        public readonly ?string $previousName = null,
    ) {
        $this->name = $name;
        $this->status = $status;
        $this->additions = $additions;
        $this->deletions = $deletions;
        $this->changes = $additions + $deletions;
        $this->patch = $patch;
    }

    public function getContent(): string
    {
        if ($this->content !== null) {
            return $this->content;
        }

        try {
            $process = new Process(['git', '--no-replace-objects', '--git-dir', $this->repository, 'cat-file', 'blob', $this->head . ':' . $this->name]);
            $process->setTimeout(120)->mustRun();

            return $this->content = $process->getOutput();
        } catch (\Throwable $exception) {
            throw new CouldNotGetFileContentException($this->name, $exception);
        }
    }
}
