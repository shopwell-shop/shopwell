<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Storefront\Controller;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Storefront\Test\Controller\StorefrontControllerTestBehaviour;
use Symfony\Component\DomCrawler\Crawler;

/**
 * @internal
 */
#[Package('discovery')]
class ContentControllerTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StorefrontControllerTestBehaviour;

    public function testWidgetRendersTheStandaloneContentLayout(): void
    {
        $layoutId = $this->createLayout('Sw:Content:Text', ['text' => '<p>Standalone content widget</p>']);

        $response = $this->request('GET', '/widgets/content/' . $layoutId, []);

        static::assertSame(200, $response->getStatusCode());
        static::assertSame('noindex', $response->headers->get('x-robots-tag'));
        static::assertStringContainsString('Standalone content widget', (string) $response->getContent());
        static::assertStringNotContainsString('<html', (string) $response->getContent());
    }

    public function testFullPageRendersTheStandaloneContentLayout(): void
    {
        $layoutId = $this->createLayout('Sw:Content:Text', ['text' => '<p>Standalone full content page</p>']);

        $response = $this->request('GET', '/page/content/' . $layoutId, []);

        static::assertSame(200, $response->getStatusCode());
        static::assertStringContainsString('Standalone full content page', (string) $response->getContent());
        static::assertStringContainsString('<html', (string) $response->getContent());
    }

    public function testContactFormRendersUsableTranslatedGenderChoices(): void
    {
        $layoutId = $this->createLayout('Sw:Form:Contact', ['title' => 'Contact us'], [
            'genders' => ['key' => 'genders', 'source' => 'form_genders', 'config' => []],
        ]);

        $response = $this->request('GET', '/widgets/content/' . $layoutId, []);
        $crawler = new Crawler((string) $response->getContent());

        static::assertSame(200, $response->getStatusCode());
        static::assertCount(1, $crawler->filter('form[data-content-form-handler]'));
        static::assertCount(1, $crawler->filter('select[name="gender"]'));
        static::assertGreaterThan(1, $crawler->filter('select[name="gender"] option')->count());
        static::assertStringContainsString('Contact us', (string) $response->getContent());
    }

    /**
     * @param array<string, mixed> $properties
     * @param array<string, mixed> $dataRequirements
     */
    private function createLayout(string $component, array $properties, array $dataRequirements = []): string
    {
        $id = Uuid::randomHex();
        static::getContainer()->get('content_layout.repository')->create([[
            'id' => $id,
            'name' => 'Standalone content test',
            'version' => '1.0.0',
            'rootSource' => 'none',
            'layout' => [[
                'id' => Uuid::randomHex(),
                'component' => $component,
                'properties' => $properties,
                'dataRequirements' => $dataRequirements,
            ]],
        ]], Context::createDefaultContext());

        return $id;
    }
}
