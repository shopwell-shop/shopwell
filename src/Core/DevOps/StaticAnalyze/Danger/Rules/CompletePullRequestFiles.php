<?php declare(strict_types=1);

namespace Shopwell\Core\DevOps\StaticAnalyze\Danger\Rules;

use Danger\Context;
use Danger\Platform\Github\Github;
use Shopwell\Core\DevOps\StaticAnalyze\Danger\GitPullRequest;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class CompletePullRequestFiles
{
    public function __construct(private readonly string $checkout = __DIR__ . '/../../../../../..')
    {
    }

    public function __invoke(Context $context): void
    {
        $platform = $context->platform;
        if (!$platform instanceof Github) {
            return;
        }
        $base = $platform->raw['base']['sha'] ?? null;
        $head = $platform->raw['head']['sha'] ?? null;
        $expected = $platform->raw['changed_files'] ?? null;
        if (!\is_string($base) || !\is_string($head) || !\is_int($expected)
            || !preg_match('/^[a-f0-9]{40}$/D', $base) || !preg_match('/^[a-f0-9]{40}$/D', $head)) {
            throw new \RuntimeException('Missing immutable GitHub PR metadata for the complete Danger inventory.');
        }
        [$owner, $repository] = explode('/', $platform->pullRequest->projectIdentifier, 2);
        $comparison = $platform->client->repo()->commits()->compare($owner, $repository, $base, $head);
        $mergeBase = $comparison['merge_base_commit']['sha'] ?? null;
        if (!\is_string($mergeBase)) {
            throw new \RuntimeException('Missing immutable merge base for the complete Danger inventory.');
        }
        $platform->pullRequest = new GitPullRequest($platform->pullRequest, $this->checkout, $mergeBase, $head, $expected);
    }
}
