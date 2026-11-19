<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Graph\Codec;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Codec\GraphCodec;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Graph\_fixtures\GraphFixture;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(GraphCodec::class)]
class GraphCodecTest extends TestCase
{
    private GraphCodec $codec;

    protected function setUp(): void
    {
        $this->codec = GraphFixture::codec();
    }

    public function testCanonicalRoundTripPreservesMapsListsAndFalseZero(): void
    {
        $graph = $this->codec->decodeDraft(GraphFixture::json());
        $encoded = $this->codec->encodeDraft($graph);

        static::assertStringContainsString('"extensions":{}', $encoded);
        static::assertStringContainsString('"fields":[]', $encoded);
        static::assertStringContainsString('"collapsed":false', $encoded);
        static::assertStringContainsString('"x":0', $encoded);
        static::assertSame($encoded, $this->codec->encodeDraft($this->codec->decodeDraft($encoded)));
        static::assertSame($encoded, $this->codec->decodeDraft(str_replace('    ', '', GraphFixture::json()))->json);
    }

    public function testKnownIncompleteConfigurationIsWritableWithoutDefaults(): void
    {
        $json = str_replace('"outcome": "approved"', '', GraphFixture::json());
        $graph = $this->codec->decodeDraft($json);

        static::assertStringContainsString('"config":{}', $this->codec->encodeDraft($graph));
        static::assertSame($graph->json, $this->codec->encodeDraft($graph));
    }

    public function testInvalidPresentConfigurationFailsRatherThanCoercing(): void
    {
        $this->expectExceptionObject(ApprovalFlowException::invalidGraph('/nodes/2/config/outcome', 'The data should match one item from enum'));

        $this->codec->decodeDraft(str_replace('"approved"', 'false', GraphFixture::json()));
    }

    public function testUnknownTypeIsPreservedForReadOnlyUseButCannotBeWritten(): void
    {
        $json = str_replace('core:approval', 'finance:missing', GraphFixture::json());
        $graph = $this->codec->decodeDraft($json);
        static::assertStringContainsString('finance:missing', $graph->json);

        $this->expectExceptionObject(ApprovalFlowException::invalidGraph('/nodes/1/type', 'Node type finance:missing@1.0.0 is unavailable.'));
        $this->codec->encodeDraft($graph);
    }

    public function testDuplicateNodeIdsAreRejected(): void
    {
        $this->expectExceptionObject(ApprovalFlowException::invalidGraph('/nodes/2/id', 'Duplicate node id start.'));

        $this->codec->decodeDraft(str_replace('"id": "end"', '"id": "start"', GraphFixture::json()));
    }

    public function testUnknownRootFieldIsRejected(): void
    {
        $this->expectException(ApprovalFlowException::class);

        $this->codec->decodeDraft(str_replace('"schemaVersion":', '"typo": 1, "schemaVersion":', GraphFixture::json()));
    }

    public function testEmptyDraftIsWritableAndDoesNotInventNodes(): void
    {
        $json = '{"schemaVersion":"1.0.0","id":"draft","meta":{"name":"Transfer","editingMode":"oa","formSchemaRef":"payment:transfer"},"nodes":[],"connections":[],"layout":{"algorithm":"core:layered","nodes":[]},"extensions":{}}';
        $graph = $this->codec->decodeDraft($json);

        static::assertStringContainsString('"nodes":[]', $this->codec->encodeDraft($graph));
        static::assertStringNotContainsString('core:start', $graph->json);
    }

    public function testUndeclaredExtensionCannotBecomeAnUntypedWriteEscapeHatch(): void
    {
        $json = str_replace('"extensions": {}', '"extensions": {"finance:settings":{"value":null}}', GraphFixture::json());
        $graph = $this->codec->decodeDraft($json);
        static::assertStringContainsString('"finance:settings":{"value":null}', $graph->json);

        $this->expectExceptionObject(ApprovalFlowException::invalidGraph('/nodes/0/extensions/finance:settings', 'Extension namespace has no registered schema.'));
        $this->codec->encodeDraft($graph);
    }

    public function testDraftSchemasRetainRequiredDiscriminators(): void
    {
        $json = str_replace('"kind": "core:all",', '', GraphFixture::json());
        $this->expectException(ApprovalFlowException::class);

        $this->codec->decodeDraft($json);
    }

    public function testMissingExactVersionCannotUseAnotherVersionOfTheNode(): void
    {
        $json = str_replace('"typeVersion": "1.0.0"', '"typeVersion": "2.0.0"', GraphFixture::json());
        $graph = $this->codec->decodeDraft($json);

        $this->expectExceptionObject(ApprovalFlowException::invalidGraph('/nodes/0/type', 'Node type core:start@2.0.0 is unavailable.'));
        $this->codec->encodeDraft($graph);
    }

    public function testUnsupportedSchemaVersionIsRejected(): void
    {
        $this->expectException(ApprovalFlowException::class);

        $this->codec->decodeDraft(str_replace('"schemaVersion": "1.0.0"', '"schemaVersion": "2.0.0"', GraphFixture::json()));
    }
}
