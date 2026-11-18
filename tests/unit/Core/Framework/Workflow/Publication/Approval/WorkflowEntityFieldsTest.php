<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Workflow\Publication\Approval;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Definition\Input\WorkflowEntityFields;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionException;
use Shopwell\Core\System\Organization\Aggregate\OrganizationUnit\OrganizationUnitCollection;
use Shopwell\Core\System\User\UserCollection;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(WorkflowEntityFields::class)]
class WorkflowEntityFieldsTest extends TestCase
{
    public function testNormalizesPeopleWithoutChangingTheSelectedOrder(): void
    {
        $first = 'abcdef0123456789abcdef0123456789';
        $second = '0123456789abcdef0123456789abcdef';
        $users = StaticEntityRepository::of(UserCollection::class, [[$first, $second]]);
        $fields = new WorkflowEntityFields($users, StaticEntityRepository::of(OrganizationUnitCollection::class));

        static::assertSame([$second, $first], $fields->normalize(['type' => 'users', 'candidateIds' => [$first, $second]], [$second, strtoupper($first)], Context::createDefaultContext()));
        static::assertSame([$second, $first], $users->searchedCriteria[0]->getIds());
    }

    public function testForgedCandidateIsRejectedBeforeReadingTheRepository(): void
    {
        $users = StaticEntityRepository::of(UserCollection::class);
        $fields = new WorkflowEntityFields($users, StaticEntityRepository::of(OrganizationUnitCollection::class));
        $this->expectException(WorkflowExecutionException::class);

        try {
            $fields->normalize(['type' => 'user', 'candidateIds' => ['abcdef0123456789abcdef0123456789']], '0123456789abcdef0123456789abcdef', Context::createDefaultContext());
        } finally {
            static::assertSame([], $users->searchedCriteria);
        }
    }

    public function testAnUnavailableAccountCannotBeSubmittedAsAReviewer(): void
    {
        $fields = new WorkflowEntityFields(StaticEntityRepository::of(UserCollection::class, [[]]), StaticEntityRepository::of(OrganizationUnitCollection::class));
        $this->expectException(WorkflowExecutionException::class);

        $fields->normalize(['type' => 'user'], '0123456789abcdef0123456789abcdef', Context::createDefaultContext());
    }

    public function testDepartmentUsesOnlyActiveUnitsInActiveOrganizations(): void
    {
        $id = '0123456789abcdef0123456789abcdef';
        $departments = StaticEntityRepository::of(OrganizationUnitCollection::class, [[$id]]);
        $fields = new WorkflowEntityFields(StaticEntityRepository::of(UserCollection::class), $departments);

        static::assertSame($id, $fields->normalize(['type' => 'department'], $id, Context::createDefaultContext()));
        static::assertEquals([new EqualsFilter('active', true), new EqualsFilter('organization.active', true)], $departments->searchedCriteria[0]->getFilters());
    }
}
