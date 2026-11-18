<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Workflow\Execution\Assignee;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\Assignee\FormFieldAssigneeResolver;
use Shopwell\Core\Framework\Workflow\Execution\Assignee\UserAssigneeResolver;
use Shopwell\Core\Framework\Workflow\Execution\Graph\GraphData;
use Shopwell\Core\Framework\Workflow\Execution\Graph\GraphNode;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionContext;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionException;
use Shopwell\Core\Framework\Workflow\Instance\WorkflowInstanceEntity;
use Shopwell\Core\System\User\UserCollection;
use Shopwell\Core\System\User\UserEntity;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(FormFieldAssigneeResolver::class)]
class FormFieldAssigneeResolverTest extends TestCase
{
    private const USER = '0123456789abcdef0123456789abcdef';

    public function testResolvesFrozenSelectionThroughCurrentUserEligibility(): void
    {
        $user = new UserEntity();
        $user->setId(self::USER);
        $user->setActive(true);
        $user->setAdmin(true);
        $repository = StaticEntityRepository::of(UserCollection::class, [new UserCollection([$user])]);

        static::assertSame([self::USER], $this->resolve(new UserAssigneeResolver($repository), [self::USER]));
        static::assertSame([self::USER], $repository->searchedCriteria[0]->getIds());
    }

    public function testAbsentOptionalSelectionUsesTheMissingAssigneePolicy(): void
    {
        $repository = StaticEntityRepository::of(UserCollection::class);

        static::assertSame([], $this->resolve(new UserAssigneeResolver($repository), []));
        static::assertSame([], $repository->searchedCriteria);
    }

    public function testForgedSelectionCannotTriggerTheMissingAssigneePolicy(): void
    {
        $repository = StaticEntityRepository::of(UserCollection::class);
        $this->expectExceptionObject(WorkflowExecutionException::invalidGraph('The selected reviewer is outside the published candidate range.'));

        try {
            $this->resolve(new UserAssigneeResolver($repository), ['fedcba9876543210fedcba9876543210']);
        } finally {
            static::assertSame([], $repository->searchedCriteria);
        }
    }

    /**
     * @param list<string> $value
     *
     * @return list<string>
     */
    private function resolve(UserAssigneeResolver $users, array $value): array
    {
        $instance = new WorkflowInstanceEntity();
        $instance->formData = ['people' => $value];
        $graph = GraphData::fromArray(['nodes' => [['id' => 'start', 'type' => 'snaker:start', 'properties' => ['inputSchema' => [
            ['key' => 'people', 'label' => 'Reviewers', 'type' => 'users', 'candidateIds' => [self::USER]],
        ]]]], 'edges' => []]);
        $context = new WorkflowExecutionContext($graph, $instance, Context::createDefaultContext(), null, 1);
        $node = new GraphNode('review', 'snaker:task', 0, 0, ['assigneeValue' => ['people']]);

        return (new FormFieldAssigneeResolver($users))->resolve($instance, $node, $context);
    }
}
