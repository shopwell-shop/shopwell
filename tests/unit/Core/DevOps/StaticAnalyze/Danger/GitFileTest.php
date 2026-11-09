<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\DevOps\StaticAnalyze\Danger;

use Danger\Exception\CouldNotGetFileContentException;
use Danger\Struct\File;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\DevOps\StaticAnalyze\Danger\GitFile;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(GitFile::class)]
class GitFileTest extends TestCase
{
    private string $repository;

    private string $head;

    protected function setUp(): void
    {
        $this->repository = sys_get_temp_dir() . '/danger-file-test-' . bin2hex(random_bytes(8));
        mkdir($this->repository);
        (new Process(['git', 'init', '--quiet', $this->repository]))->mustRun();
        file_put_contents($this->repository . '/empty.txt', '');
        file_put_contents($this->repository . '/blob.txt', "exact\0blob\n");
        (new Process(['git', 'add', '.'], $this->repository))->mustRun();
        (new Process(['git', '-c', 'user.name=Test', '-c', 'user.email=test@example.com', 'commit', '--quiet', '-m', 'fixture'], $this->repository))->mustRun();
        $process = new Process(['git', 'rev-parse', 'HEAD'], $this->repository);
        $process->mustRun();
        $this->head = trim($process->getOutput());
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->repository);
    }

    public function testContentIsExactBlobRatherThanWorktree(): void
    {
        file_put_contents($this->repository . '/blob.txt', 'untrusted');
        $file = new GitFile($this->repository . '/.git', $this->head, 'blob.txt', File::STATUS_RENAMED, 2, 1, "@@ -1 +1 @@\n-before\n+after\n", 'previous.txt');
        static::assertSame("exact\0blob\n", $file->getContent());
        static::assertSame("exact\0blob\n", $file->getContent());
        static::assertSame(File::STATUS_RENAMED, $file->status);
        static::assertSame('previous.txt', $file->previousName);
        static::assertSame(3, $file->changes);
        static::assertSame("@@ -1 +1 @@\n-before\n+after\n", $file->patch);
    }

    public function testEmptyBlobRemainsValidContent(): void
    {
        static::assertSame('', (new GitFile($this->repository . '/.git', $this->head, 'empty.txt'))->getContent());
    }

    public function testMissingBlobThrowsRatherThanReturningEmptyContent(): void
    {
        $this->expectException(CouldNotGetFileContentException::class);
        (new GitFile($this->repository . '/.git', $this->head, 'missing.txt'))->getContent();
    }

    public function testMissingCommitThrowsRatherThanReadingWorktree(): void
    {
        $this->expectException(CouldNotGetFileContentException::class);
        (new GitFile($this->repository . '/.git', str_repeat('b', 40), 'blob.txt'))->getContent();
    }
}
