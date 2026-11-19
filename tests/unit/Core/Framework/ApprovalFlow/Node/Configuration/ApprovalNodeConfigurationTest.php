<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Node\Configuration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
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
    public function testSerializesFieldRulesAsValuesAndKeepsListsInOrder(): void
    {
        $configuration = new ApprovalNodeConfiguration(
            userIds: ['second', 'first'],
            policy: ApprovalNodeConfiguration::ANY,
            fields: [new ApprovalFieldConfiguration('form.amount', ApprovalFieldConfiguration::EDITABLE, required: false)],
            emptyAssignees: ApprovalNodeConfiguration::EMPTY_INCIDENT,
        );

        $json = json_encode($configuration, \JSON_THROW_ON_ERROR);

        static::assertSame('{"userIds":["second","first"],"policy":"core:any","reject":"veto","fields":[{"field":"form.amount","access":"editable","required":false}],"emptyAssignees":"incident"}', $json);
    }
}
