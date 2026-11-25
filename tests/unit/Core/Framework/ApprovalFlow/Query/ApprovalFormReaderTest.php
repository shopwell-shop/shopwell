<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\ApprovalFlow\Query\ApprovalFormReader;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ApprovalFormReader::class)]
class ApprovalFormReaderTest extends TestCase
{
    public function testOnlyGrantedFieldsAreReturnedAndJsonShapesRemainIntact(): void
    {
        $plan = '{"nodes":[{"nodeId":"review","type":"core:approval","typeVersion":"1.0.0","configuration":{"fields":[{"field":"form.amount","access":"readOnly"},{"field":"form.empty","access":"readOnly"},{"field":"form.items","access":"editable"},{"field":"form.secret","access":"hidden"}]}}]}';

        $form = (new ApprovalFormReader())->read('{"amount":0,"empty":{},"items":[],"secret":"private","unspecified":false}', $plan, ['review']);

        static::assertSame('{"amount":0,"empty":{},"items":[]}', json_encode($form, \JSON_THROW_ON_ERROR));
    }

    public function testNestedObjectGrantsDoNotExposeTheirSiblings(): void
    {
        $plan = '{"nodes":[{"nodeId":"review","type":"core:approval","typeVersion":"1.0.0","configuration":{"fields":[{"field":"form.address.city","access":"readOnly"}]}}]}';

        $form = (new ApprovalFormReader())->read('{"address":{"city":"Shanghai","street":"private"},"address.city":"another-secret"}', $plan, ['review']);

        static::assertSame('{"address":{"city":"Shanghai"}}', json_encode($form, \JSON_THROW_ON_ERROR));
    }

    public function testExplicitHiddenRulesWinOverParentGrants(): void
    {
        $plan = '{"nodes":[{"nodeId":"review","type":"core:approval","typeVersion":"1.0.0","configuration":{"fields":[{"field":"form.address","access":"readOnly"},{"field":"form.address.street","access":"hidden"},{"field":"form.items","access":"readOnly"},{"field":"form.items.secret","access":"hidden"}]}}]}';

        $form = (new ApprovalFormReader())->read('{"address":{"city":"Shanghai","street":"private"},"items":[{"secret":"private"}]}', $plan, ['review']);

        static::assertSame('{"address":{"city":"Shanghai"}}', json_encode($form, \JSON_THROW_ON_ERROR));
    }

    public function testAVisibleChildCannotReopenAHiddenParent(): void
    {
        $plan = '{"nodes":[{"nodeId":"review","type":"core:approval","typeVersion":"1.0.0","configuration":{"fields":[{"field":"form.address","access":"hidden"},{"field":"form.address.city","access":"readOnly"}]}}]}';

        $form = (new ApprovalFormReader())->read('{"address":{"city":"Shanghai","street":"private"}}', $plan, ['review']);

        static::assertSame('{}', json_encode($form, \JSON_THROW_ON_ERROR));
    }

    public function testUnassignedAndUnsupportedNodesCannotGrantFieldAccess(): void
    {
        $plan = '{"nodes":[{"nodeId":"other","type":"core:approval","typeVersion":"1.0.0","configuration":{"fields":[{"field":"form.secret","access":"readOnly"}]}},{"nodeId":"custom","type":"finance:review","typeVersion":"1.0.0","configuration":{"fields":[{"field":"form.secret","access":"readOnly"}]}}]}';

        static::assertSame('{}', json_encode((new ApprovalFormReader())->read('{"secret":"private"}', $plan, ['custom']), \JSON_THROW_ON_ERROR));
    }

    public function testAHiddenRuleIsNotOverriddenByAnotherAssignedNode(): void
    {
        $plan = '{"nodes":[{"nodeId":"first","type":"core:approval","typeVersion":"1.0.0","configuration":{"fields":[{"field":"form.secret","access":"hidden"}]}},{"nodeId":"second","type":"core:approval","typeVersion":"1.0.0","configuration":{"fields":[{"field":"form.secret","access":"readOnly"}]}}]}';

        static::assertSame('{}', json_encode((new ApprovalFormReader())->read('{"secret":"private"}', $plan, ['first', 'second']), \JSON_THROW_ON_ERROR));
    }

    public function testNumericObjectKeysDoNotBecomeArrayOffsets(): void
    {
        $plan = '{"nodes":[{"nodeId":"review","type":"core:approval","typeVersion":"1.0.0","configuration":{"fields":[{"field":"form.1","access":"readOnly"},{"field":"form.report.2026.amount","access":"readOnly"}]}}]}';

        $form = (new ApprovalFormReader())->read('{"1":false,"secret":null,"report":{"2026":{"amount":100,"secret":true}}}', $plan, ['review']);

        static::assertSame('{"1":false,"report":{"2026":{"amount":100}}}', json_encode($form, \JSON_THROW_ON_ERROR));
    }

    public function testLabelsDescribeOnlyFieldsPresentInTheVisibleForm(): void
    {
        $schema = '{"properties":{"amount":{"title":"Amount (minor units)","x-shopwell-label-snippet":"finance.amount"},"address":{"properties":{"city":{"title":"City"},"street":{"title":"Hidden street"}}},"secret":{"title":"Secret"}}}';
        $form = json_decode('{"amount":0,"address":{"city":"Shanghai"},"note":null}', flags: \JSON_THROW_ON_ERROR);
        static::assertInstanceOf(\stdClass::class, $form);

        $labels = (new ApprovalFormReader())->labels($form, $schema);

        static::assertSame(['title' => 'Amount (minor units)', 'labelSnippet' => 'finance.amount'], $labels->{'form.amount'});
        static::assertSame(['title' => 'City', 'labelSnippet' => null], $labels->{'form.address.city'});
        static::assertFalse(property_exists($labels, 'form.secret'));
        static::assertFalse(property_exists($labels, 'form.address.street'));
    }

    public function testBooleanPropertySchemasUseFieldNamesForNestedLabels(): void
    {
        $form = (object) ['open' => (object) ['note' => 'Visible']];

        $labels = (new ApprovalFormReader())->labels($form, '{"properties":{"open":true}}');

        static::assertSame(['title' => 'open', 'labelSnippet' => null], $labels->{'form.open'});
        static::assertSame(['title' => 'note', 'labelSnippet' => null], $labels->{'form.open.note'});
    }
}
