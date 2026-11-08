<?php declare(strict_types=1);

namespace Shopwell\Core\DevOps\StaticAnalyze\Danger;

use Danger\Struct\CommentCollection;
use Danger\Struct\CommitCollection;
use Danger\Struct\File;
use Danger\Struct\FileCollection;
use Danger\Struct\PullRequest;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Process\Process;

/**
 * @internal
 */
#[Package('framework')]
class GitPullRequest extends PullRequest
{
    private readonly string $repository;

    /**
     * @var FileCollection<File>
     */
    private readonly FileCollection $files;

    public function __construct(
        private readonly PullRequest $original,
        string $checkout,
        string $mergeBase,
        private readonly string $head,
        int $expectedFiles,
    ) {
        foreach ([$mergeBase, $head] as $sha) {
            if (!preg_match('/^[a-f0-9]{40}$/D', $sha)) {
                throw new \RuntimeException('Expected an exact commit SHA for the complete Danger inventory.');
            }
        }
        if ($expectedFiles < 0) {
            throw new \RuntimeException('Expected a nonnegative changed-file count.');
        }
        $this->id = $original->id;
        $this->projectIdentifier = $original->projectIdentifier;
        $this->title = $original->title;
        $this->body = $original->body;
        $this->state = $original->state;
        $this->createdAt = $original->createdAt;
        $this->updatedAt = $original->updatedAt;
        $this->labels = $original->labels;
        $this->assignees = $original->assignees;
        $this->reviewers = $original->reviewers;
        $this->rawCommits = $original->rawCommits;

        $directory = tempnam(sys_get_temp_dir(), 'danger-git-');
        if ($directory === false) {
            throw new \RuntimeException('Could not create the complete Danger inventory repository.');
        }
        unlink($directory);
        $this->repository = $directory;

        try {
            $entries = explode("\0", $this->git($checkout, 'config', '--show-origin', '--name-only', '--null', '--list'));
            $configFiles = [];
            for ($index = 0; $index + 1 < \count($entries); $index += 2) {
                if (str_starts_with($entries[$index], 'file:')) {
                    $configFiles[Path::makeAbsolute(substr($entries[$index], 5), $checkout)] = true;
                }
            }
            $includes = [];
            foreach (array_keys($configFiles) as $file) {
                $includes[] = '-c';
                $includes[] = 'include.path=' . $file;
            }
            $this->git($checkout, 'init', '--bare', '--quiet', $directory);
            $this->git($checkout, ...[
                ...$includes, '-c', 'core.hooksPath=/dev/null', '--git-dir', $directory,
                'fetch', '--no-tags', '--depth=1', '--no-write-fetch-head', '--recurse-submodules=no', 'origin', $mergeBase, $head,
            ]);
            foreach ([$mergeBase, $head] as $sha) {
                if (trim($this->git($checkout, '--git-dir', $directory, 'rev-parse', '--verify', $sha . '^{commit}')) !== $sha) {
                    throw new \RuntimeException('Fetched commit does not match the requested Danger inventory SHA.');
                }
            }
            $this->files = $this->inventory($checkout, $mergeBase, $expectedFiles);
        } catch (\Throwable $exception) {
            (new Filesystem())->remove($directory);
            throw $exception;
        }
    }

    public function __destruct()
    {
        (new Filesystem())->remove($this->repository);
    }

    public function getFiles(): FileCollection
    {
        return $this->files;
    }

    public function getFile(string $fileName): File
    {
        return $this->files->get($fileName) ?? new GitFile($this->repository, $this->head, $fileName);
    }

    public function getFileContent(string $path): string
    {
        return $this->getFile($path)->getContent();
    }

    public function getCommits(): CommitCollection
    {
        $commits = $this->original->getCommits();
        $this->rawCommits = $this->original->rawCommits;

        return $commits;
    }

    public function getComments(): CommentCollection
    {
        return $this->original->getComments();
    }

    /**
     * @return FileCollection<File>
     */
    private function inventory(string $checkout, string $mergeBase, int $expectedFiles): FileCollection
    {
        $arguments = [
            '-c', 'core.attributesFile=/dev/null', '--git-dir', $this->repository, 'diff',
            '--no-ext-diff', '--no-textconv', '--no-color', '--no-relative', '--find-renames=50%', '-l1000',
            '--ignore-submodules=none', '--submodule=short', $mergeBase, $this->head,
        ];
        $names = explode("\0", $this->git($checkout, ...[...$arguments, '--name-status', '-z', '--']));
        array_pop($names);
        $stats = explode("\0", $this->git($checkout, ...[...$arguments, '--numstat', '-z', '--']));
        array_pop($stats);
        $patches = preg_split('/^diff --git /m', $this->git($checkout, ...[...$arguments, '--patch', '--']));
        if ($patches === false) {
            throw new \RuntimeException('Could not parse the complete Danger patches.');
        }
        array_shift($patches);
        $files = new FileCollection();
        $patchIndex = 0;
        while ($names !== []) {
            $status = array_shift($names);
            $previousName = null;
            $name = array_shift($names);
            $mappedStatus = match ($status[0]) {
                'A' => File::STATUS_ADDED,
                'D' => File::STATUS_REMOVED,
                'M', 'T' => File::STATUS_MODIFIED,
                'R' => File::STATUS_RENAMED,
                default => throw new \RuntimeException('Unsupported complete Danger file status: ' . $status),
            };
            if ($mappedStatus === File::STATUS_RENAMED) {
                $previousName = $name;
                $name = array_shift($names);
            }
            $stat = explode("\t", array_shift($stats) ?? '', 3);
            if (\count($stat) !== 3 || $name === null || $name === '' || !isset($patches[$patchIndex])) {
                throw new \RuntimeException('Incomplete Danger Git diff record.');
            }
            $statName = $stat[2];
            if ($statName === '') {
                $statPrevious = array_shift($stats);
                $statName = array_shift($stats);
                if ($statPrevious !== $previousName) {
                    throw new \RuntimeException('Danger Git rename records disagree.');
                }
            }
            if ($statName !== $name || $files->get($name) !== null) {
                throw new \RuntimeException('Danger Git file records disagree.');
            }
            foreach ([$stat[0], $stat[1]] as $count) {
                if ($count !== '-' && !ctype_digit($count)) {
                    throw new \RuntimeException('Invalid Danger Git change count.');
                }
            }
            $patch = $patches[$patchIndex++];
            $hunk = strpos($patch, "\n@@ ");
            $files->set($name, new GitFile(
                $this->repository,
                $this->head,
                $name,
                $mappedStatus,
                $stat[0] === '-' ? 0 : (int) $stat[0],
                $stat[1] === '-' ? 0 : (int) $stat[1],
                $hunk === false ? '' : substr($patch, $hunk + 1),
                $previousName,
            ));
        }
        if ($files->count() !== $expectedFiles || $stats !== [] || $patchIndex !== \count($patches)) {
            throw new \RuntimeException(\sprintf('Incomplete Danger inventory: expected %d changed files, found %d.', $expectedFiles, $files->count()));
        }

        return $files;
    }

    private function git(string $checkout, string ...$arguments): string
    {
        $process = new Process(['git', '--no-replace-objects', ...$arguments], $checkout);
        $process->setTimeout(120)->mustRun();

        return $process->getOutput();
    }
}
