<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Node\Configuration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\Node\Configuration\ApprovalFieldConfiguration;
use Shopwell\Core\Framework\ApprovalFlow\Node\Configuration\ApprovalNodeConfiguration;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ApprovalNodeConfiguration::class)]
class ApprovalNodeConfigurationTest extends TestCase
{
    public function testRequiresAtLeastOneCandidate(): void
    {
        $this->expectExceptionObject(ApprovalFlowException::emptyApprovalAssignees());

        new ApprovalNodeConfiguration([], ApprovalNodeConfiguration::ALL, [], ApprovalNodeConfiguration::EMPTY_INCIDENT);
    }

    public function testSingleApprovalCannotBeConstructedWithMultipleCandidates(): void
    {
        $this->expectExceptionObject(ApprovalFlowException::singleApprovalAssigneeRequired());

        new ApprovalNodeConfiguration(['first', 'second'], ApprovalNodeConfiguration::SINGLE, [], ApprovalNodeConfiguration::EMPTY_INCIDENT);
    }

    public function testConfigurationKeepsItsOwnCandidateAndFieldValues(): void
    {
        $userId = 'reviewer';
        $field = new ApprovalFieldConfiguration('form.amount', ApprovalFieldConfiguration::EDITABLE, required: true);
        $configuration = new ApprovalNodeConfiguration([&$userId], ApprovalNodeConfiguration::ALL, [&$field], ApprovalNodeConfiguration::EMPTY_INCIDENT);

        $userId = 'someone-else';
        $field = new ApprovalFieldConfiguration('form.amount', ApprovalFieldConfiguration::READ_ONLY, required: false);

        static::assertSame(['reviewer'], $configuration->userIds);
        static::assertSame(ApprovalFieldConfiguration::EDITABLE, $configuration->fields[0]->access);
    }

    public function testSerializesFieldRulesAsValuesAndKeepsListsInOrder(): void
    {
        $configuration = new ApprovalNodeConfiguration(
            userIds: ['second', 'first'],
            policy: ApprovalNodeConfiguration::ANY,
            fields: [new ApprovalFieldConfiguration('form.amount', ApprovalFieldConfiguration::EDITABLE, required: false)],
            emptyAssignees: ApprovalNodeConfiguration::EMPTY_INCIDENT,
        );

        $json = json_encode($configuration, \JSON_THROW_ON_ERROR);

        static::assertSame('{"userIds":["second","first"],"policy":"core:any","policyVersion":"1.0.0","reject":"veto","fields":[{"field":"form.amount","access":"editable","required":false}],"emptyAssignees":"incident"}', $json);
    }
}
