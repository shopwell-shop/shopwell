<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\DevOps\StaticAnalyze\Danger;

use Danger\Context;
use Danger\Struct\File;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\DevOps\StaticAnalyze\Danger\GitFile;
use Shopwell\Core\DevOps\StaticAnalyze\Danger\GitPullRequest;
use Shopwell\Core\DevOps\StaticAnalyze\Danger\Rules\MissingUnitTests;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Stub\StubFile;
use Shopwell\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Stub\StubPlatform;
use Shopwell\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Stub\StubPullRequest;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(GitPullRequest::class)]
class GitPullRequestTest extends TestCase
{
    private string $repository;

    protected function setUp(): void
    {
        $this->repository = sys_get_temp_dir() . '/danger-inventory-test-' . bin2hex(random_bytes(8));
        mkdir($this->repository);
        $this->git('init', '--quiet');
        $this->git('config', 'user.email', 'test@example.com');
        $this->git('config', 'user.name', 'Test');
        $this->git('remote', 'add', 'origin', $this->repository);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->repository);
    }

    public function testCompleteInventoryFindsMatchingTestBeyondApiLimitAndKeepsRealMissingTests(): void
    {
        $this->git('commit', '--allow-empty', '--quiet', '-m', 'base');
        $base = trim($this->git('rev-parse', 'HEAD'));
        mkdir($this->repository . '/docs');
        for ($i = 0; $i < 2998; ++$i) {
            file_put_contents($this->repository . '/docs/' . $i . '.txt', "documentation\n");
        }
        mkdir($this->repository . '/src/Core/Fixture', 0777, true);
        mkdir($this->repository . '/tests/unit/Core/Fixture', 0777, true);
        $service = '<?php class InventoryService { public function run(): int { return 1; } }';
        file_put_contents($this->repository . '/src/Core/Fixture/InventoryService.php', $service);
        file_put_contents($this->repository . '/src/Core/Fixture/UntestedService.php', str_replace('InventoryService', 'UntestedService', $service));
        $test = '<?php class InventoryServiceTest extends TestCase {}';
        file_put_contents($this->repository . '/tests/unit/Core/Fixture/InventoryServiceTest.php', $test);
        $this->git('add', '.');
        $this->git('commit', '--quiet', '-m', 'head');
        $head = trim($this->git('rev-parse', 'HEAD'));
        $names = explode("\n", trim($this->git('diff', '--name-only', $base, $head)));
        static::assertCount(3001, $names);
        static::assertSame('tests/unit/Core/Fixture/InventoryServiceTest.php', $names[3000]);
        $truncated = new StubPullRequest(array_map(
            fn (string $path) => new StubFile($path, File::STATUS_ADDED, (string) file_get_contents($this->repository . '/' . $path)),
            \array_slice($names, 0, 3000),
        ));
        $context = new Context(new StubPlatform($truncated));
        (new MissingUnitTests())($context);
        static::assertStringContainsString('InventoryService.php', implode('\n', $context->getFailures()));
        static::assertStringContainsString('UntestedService.php', implode('\n', $context->getFailures()));

        $complete = new GitPullRequest($truncated, $this->repository, $base, $head, 3001);
        static::assertCount(3001, $complete->getFiles());
        $context = new Context(new StubPlatform($complete));
        (new MissingUnitTests())($context);
        static::assertStringNotContainsString('InventoryService.php', implode('\n', $context->getFailures()));
        static::assertStringContainsString('UntestedService.php', implode('\n', $context->getFailures()));
    }

    public function testStatusesPatchesBinaryAndUnusualPathsComeFromExactTrees(): void
    {
        file_put_contents($this->repository . '/rename old.txt', "same\n");
        file_put_contents($this->repository . '/removed.txt', "removed\n");
        file_put_contents($this->repository . '/modified.txt', "before\n");
        file_put_contents($this->repository . '/mode.txt', "mode\n");
        $this->git('add', '.');
        $this->git('commit', '--quiet', '-m', 'base');
        $base = trim($this->git('rev-parse', 'HEAD'));
        rename($this->repository . '/rename old.txt', $this->repository . '/rename new.txt');
        unlink($this->repository . '/removed.txt');
        file_put_contents($this->repository . '/modified.txt', "after\n");
        file_put_contents($this->repository . "/tab\tline\nname.txt", "unusual\n");
        file_put_contents($this->repository . '/binary.dat', "\0binary");
        chmod($this->repository . '/mode.txt', 0755);
        $this->git('add', '.');
        $this->git('commit', '--quiet', '-m', 'head');
        $head = trim($this->git('rev-parse', 'HEAD'));
        $original = new StubPullRequest([], [], ['major-php']);
        $original->body = 'Preserve description';
        $complete = new GitPullRequest($original, $this->repository, $base, $head, 6);
        static::assertCount(6, $complete->getFiles());
        static::assertSame('Preserve description', $complete->body);
        static::assertSame(['major-php'], $complete->labels);
        static::assertSame(File::STATUS_RENAMED, $complete->getFile('rename new.txt')->status);
        $renamed = $complete->getFile('rename new.txt');
        static::assertInstanceOf(GitFile::class, $renamed);
        static::assertSame('rename old.txt', $renamed->previousName);
        static::assertSame('', $complete->getFile('rename new.txt')->patch);
        static::assertSame(File::STATUS_REMOVED, $complete->getFile('removed.txt')->status);
        static::assertStringContainsString('-removed', $complete->getFile('removed.txt')->patch);
        static::assertSame(1, $complete->getFile('removed.txt')->deletions);
        static::assertSame(File::STATUS_MODIFIED, $complete->getFile('modified.txt')->status);
        static::assertSame(2, $complete->getFile('modified.txt')->changes);
        static::assertStringContainsString("-before\n+after", $complete->getFile('modified.txt')->patch);
        static::assertSame(File::STATUS_MODIFIED, $complete->getFile('mode.txt')->status);
        static::assertSame('', $complete->getFile('mode.txt')->patch);
        static::assertSame(0, $complete->getFile('mode.txt')->changes);
        static::assertSame("unusual\n", $complete->getFileContent("tab\tline\nname.txt"));
        static::assertSame("\0binary", $complete->getFileContent('binary.dat'));
        static::assertSame(0, $complete->getFile('binary.dat')->changes);
        static::assertSame('', $complete->getFile('binary.dat')->patch);
        file_put_contents($this->repository . '/modified.txt', 'untrusted working tree');
        static::assertSame("after\n", $complete->getFileContent('modified.txt'));
        static::assertCount(0, $complete->getComments());
        static::assertCount(0, $complete->getCommits());
    }

    public function testEditedRenameKeepsItsPreviousPathPatchAndLineCounts(): void
    {
        file_put_contents($this->repository . '/old.txt', "first long line\nsecond long line\nthird long line\nfourth long line\nfifth long line\n");
        $this->git('add', '.');
        $this->git('commit', '--quiet', '-m', 'base');
        $base = trim($this->git('rev-parse', 'HEAD'));
        rename($this->repository . '/old.txt', $this->repository . '/new.txt');
        file_put_contents($this->repository . '/new.txt', "first long line\nsecond long line\nchanged third line\nfourth long line\nfifth long line\n");
        $this->git('add', '.');
        $this->git('commit', '--quiet', '-m', 'head');
        $head = trim($this->git('rev-parse', 'HEAD'));
        $complete = new GitPullRequest(new StubPullRequest(), $this->repository, $base, $head, 1);
        $file = $complete->getFile('new.txt');
        static::assertInstanceOf(GitFile::class, $file);
        static::assertSame(File::STATUS_RENAMED, $file->status);
        static::assertSame('old.txt', $file->previousName);
        static::assertSame(1, $file->additions);
        static::assertSame(1, $file->deletions);
        static::assertStringContainsString("-third long line\n+changed third line", $file->patch);
        static::assertStringContainsString('changed third line', $file->getContent());
    }

    public function testUnchangedFileContentAlsoUsesImmutableHead(): void
    {
        file_put_contents($this->repository . '/unchanged.txt', "original\n");
        $this->git('add', '.');
        $this->git('commit', '--quiet', '-m', 'base');
        $head = trim($this->git('rev-parse', 'HEAD'));
        $complete = new GitPullRequest(new StubPullRequest(), $this->repository, $head, $head, 0);
        file_put_contents($this->repository . '/unchanged.txt', 'working tree');
        static::assertSame("original\n", $complete->getFileContent('unchanged.txt'));
        static::assertCount(0, $complete->getFiles());
    }

    public function testFetchUsesEffectiveConditionalGitConfiguration(): void
    {
        $this->git('commit', '--allow-empty', '--quiet', '-m', 'base');
        $head = trim($this->git('rev-parse', 'HEAD'));
        $this->git('remote', 'remove', 'origin');
        $included = $this->repository . '/.git/included-config';
        file_put_contents($included, '[remote "origin"]' . "\n\turl = " . $this->repository . "\n");
        $this->git('config', 'includeIf.gitdir:' . realpath($this->repository . '/.git') . '.path', $included);
        $complete = new GitPullRequest(new StubPullRequest(), $this->repository, $head, $head, 0);
        static::assertCount(0, $complete->getFiles());
        static::assertSame($head, trim($this->git('rev-parse', 'HEAD')));
        static::assertSame('', $this->git('status', '--porcelain'));
    }

    #[DataProvider('invalidInventoryProvider')]
    public function testIncompleteInventoryFailsClosed(string $base, string $head, int $expected): void
    {
        $this->git('commit', '--allow-empty', '--quiet', '-m', 'base');
        $actual = trim($this->git('rev-parse', 'HEAD'));
        $this->expectException(\RuntimeException::class);
        new GitPullRequest(new StubPullRequest(), $this->repository, $base === 'actual' ? $actual : $base, $head === 'actual' ? $actual : $head, $expected);
    }

    public static function invalidInventoryProvider(): \Generator
    {
        yield 'invalid base' => ['--upload-pack=bad', 'actual', 0];
        yield 'invalid head' => ['actual', 'HEAD', 0];
        yield 'missing base object' => [str_repeat('a', 40), 'actual', 0];
        yield 'missing head object' => ['actual', str_repeat('b', 40), 0];
        yield 'missing inventory entry' => ['actual', 'actual', 1];
        yield 'negative expected count' => ['actual', 'actual', -1];
    }

    public function testFetchFailureCannotUseExistingLocalObjects(): void
    {
        $this->git('commit', '--allow-empty', '--quiet', '-m', 'base');
        $head = trim($this->git('rev-parse', 'HEAD'));
        $this->git('remote', 'set-url', 'origin', $this->repository . '/missing-repository');
        $this->expectException(\RuntimeException::class);
        new GitPullRequest(new StubPullRequest(), $this->repository, $head, $head, 0);
    }

    private function git(string ...$arguments): string
    {
        $process = new Process(['git', ...$arguments], $this->repository);
        $process->mustRun();

        return $process->getOutput();
    }
}
