<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Content\LandingPage\SalesChannel;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopwell\Core\Framework\Util\Base64;
use Shopwell\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * @internal
 */
#[Package('discovery')]
#[Group('store-api')]
class LandingPageRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private KernelBrowser $browser;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
        ]);

        $this->createData();
    }

    public function testGetLandingPage(): void
    {
        $this->browser->request(
            'GET',
            '/store-api/landing-page/' . $this->ids->get('landing-page')
        );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());
        static::assertSame($this->ids->get('landing-page'), $response['id']);
        static::assertSame('My landing page', $response['name']);
    }

    public function testPostLandingPage(): void
    {
        $this->browser->request(
            'POST',
            '/store-api/landing-page/' . $this->ids->get('landing-page')
        );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());
        static::assertSame($this->ids->get('landing-page'), $response['id']);
    }

    /**
     * @param array<string, mixed> $parameters
     */
    #[DataProvider('requestParameterProvider')]
    public function testCompressedCriteriaIsReadLikeTheRequestBody(array $parameters): void
    {
        $url = '/store-api/landing-page/' . $this->ids->get('landing-page');

        $this->browser->request('POST', $url);
        $withoutParameters = $this->decodeResponse();

        $this->browser->request('POST', $url, $parameters);
        $expected = $this->decodeResponse();
        static::assertNotSame($withoutParameters, $expected, 'The parameters have to change the response');

        $compressed = gzencode(json_encode($parameters, \JSON_THROW_ON_ERROR));
        static::assertNotFalse($compressed, 'Gzip compressing failed');

        $this->browser->request('GET', $url, ['_criteria' => Base64::urlEncode($compressed)]);

        static::assertSame($expected, $this->decodeResponse());
    }

    /**
     * @return iterable<string, array{parameters: array<string, mixed>}>
     */
    public static function requestParameterProvider(): iterable
    {
        yield 'included fields of the response' => [
            'parameters' => ['includes' => ['landing_page' => ['id', 'name']]],
        ];
    }

    private function createData(): void
    {
        static::getContainer()->get('landing_page.repository')->create([[
            'id' => $this->ids->create('landing-page'),
            'name' => 'My landing page',
            'url' => 'my-landing-page',
            'active' => true,
            'salesChannels' => [['id' => $this->ids->get('sales-channel')]],
        ]], Context::createDefaultContext());
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeResponse(): array
    {
        $content = $this->browser->getResponse()->getContent();
        static::assertIsString($content);

        return json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
    }
}
