<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\App\Mcp\Feature;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\App\AppException;
use Shopwell\Core\Framework\App\Feature\TranslatedString;
use Shopwell\Core\Framework\App\Manifest\Manifest;
use Shopwell\Core\Framework\App\Mcp\Feature\McpToolConfig;
use Shopwell\Core\Framework\App\Mcp\Feature\McpToolFeatureDefinition;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Util\Filesystem;
use Shopwell\Tests\Unit\Core\Framework\App\AppFixture;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(McpToolFeatureDefinition::class)]
class McpToolFeatureDefinitionTest extends TestCase
{
    private McpToolFeatureDefinition $definition;

    protected function setUp(): void
    {
        $this->definition = new McpToolFeatureDefinition();
    }

    public function testType(): void
    {
        static::assertSame('mcp_tool', $this->definition->getType());
        static::assertSame(McpToolConfig::class, $this->definition->getConfigClass());
    }

    public function testAnAppWithoutMcpXmlDeclaresNothing(): void
    {
        static::assertSame([], $this->definition->fromApp(
            static::createStub(Manifest::class),
            new Filesystem(__DIR__),
            'en-GB',
        ));
    }

    public function testToolsAreReadFromMcpXml(): void
    {
        $configs = $this->definition->fromApp(
            static::createStub(Manifest::class),
            new Filesystem(__DIR__ . '/../../_fixtures'),
            'en-GB',
        );

        static::assertEquals([
            new McpToolConfig(
                'sync-orders',
                'https://app.example.com/mcp/sync-orders',
                ['order:read', 'order:update'],
                [
                    'since' => ['type' => 'string', 'description' => 'ISO date', 'required' => true],
                    'limit' => ['type' => 'integer'],
                ],
                new TranslatedString(['en-GB' => 'Sync Orders', 'de-DE' => 'Bestellungen synchronisieren']),
                new TranslatedString(['en-GB' => 'Imports new orders from the ERP', 'de-DE' => 'Importiert neue Bestellungen aus dem ERP']),
            ),
            new McpToolConfig(
                'stock-check',
                'https://app.example.com/mcp/stock-check',
                [],
                null,
                new TranslatedString(['en-GB' => 'Stock Check']),
                new TranslatedString([]),
            ),
        ], $configs);
    }

    public function testTheSystemLocaleGetsTheClosestDeclaredTranslation(): void
    {
        $configs = $this->definition->fromApp(
            static::createStub(Manifest::class),
            new Filesystem(__DIR__ . '/../../_fixtures'),
            'de-AT',
        );

        static::assertSame('Bestellungen synchronisieren', $configs[0]->label->forLocale('de-AT'));
        static::assertSame('Importiert neue Bestellungen aus dem ERP', $configs[0]->description->forLocale('de-AT'));
    }

    public function testAToolMayRequirePrivilegesTheAppIsGranted(): void
    {
        $this->expectNotToPerformAssertions();

        $manifest = AppFixture::createManifest();
        $manifest->addPermissions(['order' => ['read', 'update']]);

        $this->definition->validate($this->declaredTools(), AppFixture::createInstallContext(AppFixture::createAppEntity(), $manifest));
    }

    public function testAToolMayNotRequireAPrivilegeTheAppIsNotGranted(): void
    {
        $this->expectException(AppException::class);
        $this->expectExceptionMessageMatches('/requires "order:update" but it is not declared in <permissions>/');

        $manifest = AppFixture::createManifest();
        $manifest->addPermissions(['order' => ['read']]);

        $this->definition->validate($this->declaredTools(), AppFixture::createInstallContext(AppFixture::createAppEntity(), $manifest));
    }

    public function testAToolMayNotRequireAPrivilegeWhenTheAppDeclaresNoPermissions(): void
    {
        $this->expectException(AppException::class);
        $this->expectExceptionMessageMatches('/requires "order:read" but it is not declared in <permissions>/');

        $this->definition->validate($this->declaredTools(), AppFixture::createInstallContext(AppFixture::createAppEntity(), AppFixture::createManifest()));
    }

    public function testAToolWithoutRequiredPrivilegesIsAcceptedWhenTheAppDeclaresNoPermissions(): void
    {
        $this->expectNotToPerformAssertions();

        $tool = new McpToolConfig('stock-check', 'https://app.example.com/mcp/stock-check', [], null, new TranslatedString(['en-GB' => 'Stock Check']), new TranslatedString([]));

        $this->definition->validate([$tool], AppFixture::createInstallContext(AppFixture::createAppEntity(), AppFixture::createManifest()));
    }

    public function testAStoredToolIsReplacedByTheDeclaredOne(): void
    {
        $declared = new McpToolConfig(
            'sync-orders',
            'https://app.example.com/mcp/sync-orders',
            ['order:read'],
            ['since' => ['type' => 'string', 'required' => true]],
            new TranslatedString(['en-GB' => 'Sync Orders']),
            new TranslatedString(['en-GB' => 'Imports new orders from the ERP']),
        );

        $stored = new McpToolConfig('sync-orders', 'https://stale.example.com', [], null, new TranslatedString(['en-GB' => 'Old']), new TranslatedString([]));

        $payload = $this->definition->toPayload($declared, $stored);
        $hydrated = $this->definition->fromPayload($payload);

        static::assertEquals($declared, $hydrated);
    }

    /**
     * @return list<McpToolConfig>
     */
    private function declaredTools(): array
    {
        return $this->definition->fromApp(
            static::createStub(Manifest::class),
            new Filesystem(__DIR__ . '/../../_fixtures'),
            'en-GB',
        );
    }
}
