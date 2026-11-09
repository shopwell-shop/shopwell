<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Rules;

use Composer\Autoload\ClassLoader;
use Danger\Config;
use Danger\Context;
use Danger\Platform\Github\Github;
use Danger\Platform\Github\GithubCommenter;
use Danger\Runner;
use Github\Api\PullRequest as PullRequestApi;
use Github\Api\PullRequest\Review;
use Github\Api\Repo;
use Github\Api\Repository\Commits;
use Github\Client;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\DevOps\StaticAnalyze\Danger\GitPullRequest;
use Shopwell\Core\DevOps\StaticAnalyze\Danger\Rules\CompletePullRequestFiles;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Stub\StubPlatform;
use Shopwell\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Stub\StubPullRequest;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CompletePullRequestFiles::class)]
class CompletePullRequestFilesTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        $loader = new ClassLoader();
        $loader->addPsr4('Github\\', \dirname(__DIR__, 7) . '/vendor-bin/danger-php/vendor/knplabs/github-api/lib/Github');
        $loader->register();
    }

    public function testGithubPlatformAndMetadataRemainAvailableToAllFollowingRules(): void
    {
        $directory = sys_get_temp_dir() . '/danger-rule-test-' . bin2hex(random_bytes(8));
        mkdir($directory);
        try {
            (new Process(['git', 'init', '--quiet', $directory]))->mustRun();
            (new Process(['git', 'remote', 'add', 'origin', $directory], $directory))->mustRun();
            (new Process(['git', '-c', 'user.name=Test', '-c', 'user.email=test@example.com', 'commit', '--allow-empty', '--quiet', '-m', 'base'], $directory))->mustRun();
            $process = new Process(['git', 'rev-parse', 'HEAD'], $directory);
            $process->mustRun();
            $sha = trim($process->getOutput());
            $platform = $this->platform(['base' => ['sha' => $sha], 'head' => ['sha' => $sha], 'changed_files' => 0], $sha);
            $original = $platform->pullRequest;
            $original->body = 'Reviewed description';
            $context = new Context($platform);
            $config = (new Config())
                ->useRule(new CompletePullRequestFiles($directory))
                ->useRule(static function (Context $context): void {
                    $context->notice($context->platform->pullRequest->body);
                });
            (new Runner())->run($config, $context);
            static::assertSame($platform, $context->platform);
            static::assertInstanceOf(GitPullRequest::class, $platform->pullRequest);
            static::assertSame(['Reviewed description'], $context->getNotices());
            static::assertSame($original->id, $platform->pullRequest->id);
            static::assertCount(0, $platform->pullRequest->getFiles());
        } finally {
            unset($platform, $context);
            (new Filesystem())->remove($directory);
        }
    }

    /**
     * @param array<string, mixed> $raw
     */
    #[DataProvider('invalidMetadataProvider')]
    public function testIncompleteMetadataAbortsBeforeFollowingRules(array $raw, ?string $mergeBase): void
    {
        $platform = $this->platform($raw, $mergeBase);
        $original = $platform->pullRequest;
        $context = new Context($platform);
        $config = (new Config())
            ->useRule(new CompletePullRequestFiles())
            ->useRule(static fn (Context $context) => $context->notice('must not run'));
        try {
            (new Runner())->run($config, $context);
            static::fail('Incomplete immutable metadata must abort Danger.');
        } catch (\RuntimeException) {
            static::assertSame($original, $platform->pullRequest);
            static::assertFalse($context->hasNotices());
        }
    }

    public static function invalidMetadataProvider(): \Generator
    {
        $sha = str_repeat('a', 40);
        yield 'missing metadata' => [[], null];
        yield 'mutable base' => [['base' => ['sha' => 'main'], 'head' => ['sha' => $sha], 'changed_files' => 1], null];
        yield 'missing file count' => [['base' => ['sha' => $sha], 'head' => ['sha' => $sha]], null];
        yield 'noninteger file count' => [['base' => ['sha' => $sha], 'head' => ['sha' => $sha], 'changed_files' => '1'], null];
        yield 'missing merge base' => [['base' => ['sha' => $sha], 'head' => ['sha' => $sha], 'changed_files' => 1], null];
        yield 'invalid merge base' => [['base' => ['sha' => $sha], 'head' => ['sha' => $sha], 'changed_files' => 1], 'main'];
    }

    public function testOtherPlatformsKeepTheirNativeInventory(): void
    {
        $platform = new StubPlatform(new StubPullRequest());
        $original = $platform->pullRequest;
        (new CompletePullRequestFiles())(new Context($platform));
        static::assertSame($original, $platform->pullRequest);
    }

    /**
     * @param array<string, mixed> $raw
     */
    private function platform(array $raw, ?string $mergeBase): Github
    {
        $commits = static::createStub(Commits::class);
        $commits->method('compare')->willReturn($mergeBase === null ? [] : ['merge_base_commit' => ['sha' => $mergeBase]]);
        $repo = static::createStub(Repo::class);
        $repo->method('commits')->willReturn($commits);
        $reviews = static::createStub(Review::class);
        $reviews->method('all')->willReturn([]);
        $pulls = static::createStub(PullRequestApi::class);
        $pulls->method('show')->willReturn(array_replace([
            'title' => 'PR title', 'body' => 'PR description', 'state' => 'open', 'merged' => false,
            'labels' => [], 'assignees' => [], 'requested_reviewers' => [],
            'created_at' => '2026-01-01T00:00:00Z', 'updated_at' => '2026-01-01T00:00:00Z',
            'head' => ['sha' => str_repeat('a', 40)],
        ], $raw));
        $pulls->method('reviews')->willReturn($reviews);
        $client = static::createStub(Client::class);
        $client->method('__call')->willReturnMap([['repo', [], $repo], ['pullRequest', [], $pulls]]);
        $platform = new Github($client, static::createStub(GithubCommenter::class));
        $platform->load('shopwell/shopwell', '1');

        return $platform;
    }
}
