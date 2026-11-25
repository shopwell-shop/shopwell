<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Execution\Instance;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalOutcome;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalInstanceService;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalInstanceStatus;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Policy\ApprovalPolicyHandler;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Task\ApprovalTaskStatus;
use Shopwell\Core\Framework\ApprovalFlow\Publication\ApprovalReleaseCompiler;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\Framework\Test\TestCaseHelper\TestUser;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Execution\Instance\_fixtures\ApprovalRuntimeFixture;
use Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Execution\Instance\_fixtures\InstanceFixture;
use Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Graph\_fixtures\GraphFixture;
use Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Publication\_fixtures\PublicationFixture;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('framework')]
class ApprovalPolicyExtensionTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private Filesystem $filesystem;

    private string $directory;

    private ContainerBuilder $runtime;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->directory = sys_get_temp_dir() . '/approval-policy-' . Uuid::randomHex();
        $manifest = PublicationFixture::writeManifest($this->filesystem, $this->directory, [MajorityPolicy::class, IgnoringVetoPolicy::class]);
        $this->runtime = ApprovalRuntimeFixture::container(static::getContainer(), $manifest, static function (ContainerBuilder $container): void {
            $container->register(MajorityPolicy::class)->addTag('approval_flow.approval_policy');
            $container->register(IgnoringVetoPolicy::class)->addTag('approval_flow.approval_policy');
        });
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->directory);
    }

    public function testPublicationDiscoversAndPinsThePolicyFromItsOwnTag(): void
    {
        $releaseId = ApprovalRuntimeFixture::publish(static::getContainer(), $this->runtime, $this->graph('1.0.0'));
        $row = static::getContainer()->get(Connection::class)->fetchAssociative(
            'SELECT node_plan_json, dependencies_json FROM approval_flow_release WHERE id = :id',
            ['id' => Uuid::fromHexToBytes($releaseId)],
        );
        static::assertIsArray($row);
        $plan = json_decode($row['node_plan_json'], associative: true, flags: \JSON_THROW_ON_ERROR);
        static::assertSame([['type' => 'example:majority', 'version' => '1.0.0']], $plan['policies']);
        $dependencies = json_decode($row['dependencies_json'], associative: true, flags: \JSON_THROW_ON_ERROR);
        static::assertContains(MajorityPolicy::class, array_column($dependencies['services'], 'class'));
    }

    public function testTaggedPolicyControlsRealVotesAndKeepsTheDecisionIdempotent(): void
    {
        $connection = static::getContainer()->get(Connection::class);
        [$instanceId, $executionId, $reviewers] = $this->openApproval();
        $service = $this->runtime->get(ApprovalInstanceService::class);

        foreach ([$reviewers[0], $reviewers[1]] as $index => $reviewerId) {
            $source = new AdminApiSource($reviewerId);
            $source->setPermissions([ApprovalInstanceService::DECIDE_PRIVILEGE]);
            $context = Context::createDefaultContext($source);
            $taskId = Uuid::fromBytesToHex($connection->fetchOne('SELECT id FROM approval_flow_task WHERE assignee_id = :user', ['user' => Uuid::fromHexToBytes($reviewerId)]));
            $commandId = Uuid::randomHex();
            $receipt = $service->decide($instanceId, $taskId, $commandId, 3 + $index, ApprovalOutcome::Approved, '', $context);
            static::assertEquals($receipt, $service->decide($instanceId, $taskId, $commandId, 3 + $index, ApprovalOutcome::Approved, '', $context));
            static::assertSame($index === 0 ? null : 'approved', $connection->fetchOne('SELECT outcome FROM approval_flow_execution WHERE id = :id', ['id' => Uuid::fromHexToBytes($executionId)]));
        }

        static::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM approval_flow_task WHERE status = :status', ['status' => 'cancelled']));
        static::assertSame('example:majority', $connection->fetchOne('SELECT approval_policy FROM approval_flow_execution WHERE id = :id', ['id' => Uuid::fromHexToBytes($executionId)]));
        static::assertSame('1.0.0', $connection->fetchOne('SELECT approval_policy_version FROM approval_flow_execution WHERE id = :id', ['id' => Uuid::fromHexToBytes($executionId)]));
    }

    public function testChangedPolicyArtifactsCannotCommitAnOpinion(): void
    {
        $connection = static::getContainer()->get(Connection::class);
        [$instanceId, $executionId, $reviewers] = $this->openApproval();
        $taskId = Uuid::fromBytesToHex($connection->fetchOne('SELECT id FROM approval_flow_task WHERE assignee_id = :user', ['user' => Uuid::fromHexToBytes($reviewers[0])]));
        $source = new AdminApiSource($reviewers[0]);
        $source->setPermissions([ApprovalInstanceService::DECIDE_PRIVILEGE]);
        $this->filesystem->dumpFile($this->directory . '/core.artifact', 'Changed policy deployment.');

        try {
            $this->runtime->get(ApprovalInstanceService::class)->decide($instanceId, $taskId, Uuid::randomHex(), 3, ApprovalOutcome::Rejected, '', Context::createDefaultContext($source));
            static::fail('A changed executable policy must not decide a frozen group.');
        } catch (ApprovalFlowException $exception) {
            static::assertSame(ApprovalFlowException::INVALID_ARTIFACT_MANIFEST, $exception->getErrorCode());
        }
        static::assertSame(3, (int) $connection->fetchOne('SELECT aggregate_version FROM approval_flow_instance'));
        static::assertSame(3, (int) $connection->fetchOne('SELECT COUNT(*) FROM approval_flow_task WHERE status = :status', ['status' => 'pending']));
        static::assertNull($connection->fetchOne('SELECT outcome FROM approval_flow_execution WHERE id = :id', ['id' => Uuid::fromHexToBytes($executionId)]));
    }

    public function testAPolicyCannotApproveAgainstThePublishedVeto(): void
    {
        $connection = static::getContainer()->get(Connection::class);
        [$instanceId, , $reviewers] = $this->openApproval('example:ignores-veto');
        $taskId = Uuid::fromBytesToHex($connection->fetchOne('SELECT id FROM approval_flow_task WHERE assignee_id = :user', ['user' => Uuid::fromHexToBytes($reviewers[0])]));
        $source = new AdminApiSource($reviewers[0]);
        $source->setPermissions([ApprovalInstanceService::DECIDE_PRIVILEGE]);

        try {
            $this->runtime->get(ApprovalInstanceService::class)->decide($instanceId, $taskId, Uuid::randomHex(), 3, ApprovalOutcome::Rejected, '', Context::createDefaultContext($source));
            static::fail('An extension cannot contradict its published rejection contract.');
        } catch (ApprovalFlowException $exception) {
            static::assertSame(ApprovalFlowException::INVALID_APPROVAL_POLICY_RESULT, $exception->getErrorCode());
        }
        static::assertSame(3, (int) $connection->fetchOne('SELECT aggregate_version FROM approval_flow_instance'));
        static::assertSame(3, (int) $connection->fetchOne('SELECT COUNT(*) FROM approval_flow_task WHERE status = :status', ['status' => 'pending']));
    }

    public function testUnavailableExactPolicyVersionCannotPublish(): void
    {
        $this->expectExceptionObject(ApprovalFlowException::approvalPolicyNotFound('example:majority', '2.0.0'));
        $this->runtime->get(ApprovalReleaseCompiler::class)->compile(GraphFixture::codec()->decodeDraft($this->graph('2.0.0')));
    }

    /**
     * @return array{string, string, list<string>}
     */
    private function openApproval(string $policy = 'example:majority'): array
    {
        $connection = static::getContainer()->get(Connection::class);
        $reviewers = [];
        for ($index = 0; $index < 3; ++$index) {
            $reviewers[] = TestUser::createNewTestUser($connection)->getUserId();
        }
        $graph = str_replace('example:majority', $policy, $this->graph('1.0.0', $reviewers));
        $releaseId = ApprovalRuntimeFixture::publish(static::getContainer(), $this->runtime, $graph);
        $instanceId = InstanceFixture::create($connection, $releaseId, ApprovalInstanceStatus::Running);
        $service = $this->runtime->get(ApprovalInstanceService::class);
        $service->enterStart($instanceId, Uuid::randomHex(), 1, Context::createDefaultContext());
        $executionId = Uuid::fromBytesToHex($connection->fetchOne('SELECT id FROM approval_flow_execution WHERE node_id = :node', ['node' => 'review']));
        $service->enterApproval($instanceId, $executionId, Uuid::randomHex(), 2, Context::createDefaultContext());

        return [$instanceId, $executionId, $reviewers];
    }

    /**
     * @param list<string> $reviewers
     */
    private function graph(string $version, array $reviewers = []): string
    {
        /** @var object{nodes: list<object{config: object{policy: \stdClass, assignees: list<object{userIds: list<string>}&\stdClass>}&\stdClass}&\stdClass>}&\stdClass $graph */
        $graph = json_decode(GraphFixture::json(), flags: \JSON_THROW_ON_ERROR);
        if ($reviewers !== []) {
            $graph->nodes[1]->config->assignees[0]->userIds = $reviewers;
        }
        $graph->nodes[1]->config->policy = (object) ['kind' => 'example:majority', 'version' => $version, 'reject' => 'veto'];

        return json_encode($graph, \JSON_THROW_ON_ERROR);
    }
}

/**
 * @internal
 */
class MajorityPolicy implements ApprovalPolicyHandler
{
    public function getType(): string
    {
        return 'example:majority';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function evaluate(array $statuses): ?ApprovalOutcome
    {
        if (\in_array(ApprovalTaskStatus::Rejected, $statuses, true)) {
            return ApprovalOutcome::Rejected;
        }
        $approvals = \count(array_filter($statuses, static fn (ApprovalTaskStatus $status): bool => $status === ApprovalTaskStatus::Approved));

        return $approvals > \count($statuses) / 2 ? ApprovalOutcome::Approved : null;
    }
}

/**
 * @internal
 */
class IgnoringVetoPolicy extends MajorityPolicy
{
    public function getType(): string
    {
        return 'example:ignores-veto';
    }

    public function evaluate(array $statuses): ?ApprovalOutcome
    {
        return ApprovalOutcome::Approved;
    }
}
