<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Execution;

use Psr\Log\LoggerInterface;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalInstanceService;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalInstanceStore;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;

/**
 * Resumes committed approval outcomes across the instance's active execution frontier without borrowing a personal decision's transaction or identity.
 *
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalProgressionRunnerTest
 */
#[Package('framework')]
class ApprovalProgressionRunner
{
    private const BATCH_SIZE = 100;

    public function __construct(
        private readonly ApprovalInstanceStore $store,
        private readonly ApprovalInstanceService $instances,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function run(): void
    {
        $after = null;
        $context = Context::createCLIContext();
        do {
            $pending = $this->store->pendingProgressions($after, self::BATCH_SIZE);
            foreach ($pending as $candidate) {
                // Advance the cursor on failure too, so one broken deployment cannot starve later instances.
                $after = $candidate['instanceId'];
                do {
                    try {
                        $this->instances->progress(
                            $candidate['instanceId'],
                            $candidate['executionId'],
                            $candidate['executionId'],
                            $candidate['version'],
                            $context,
                        );
                    } catch (\Throwable $exception) {
                        if (!$exception instanceof ApprovalFlowException || $exception->getErrorCode() !== ApprovalFlowException::INSTANCE_VERSION_CONFLICT) {
                            $this->logger->error('Approval progression failed.', [
                                'instanceId' => $candidate['instanceId'],
                                'executionId' => $candidate['executionId'],
                                'exception' => $exception,
                            ]);
                        }
                        break;
                    }
                    $candidate = $this->store->nextProgression($candidate['instanceId']);
                } while ($candidate !== null);
            }
        } while (\count($pending) === self::BATCH_SIZE);
    }
}
