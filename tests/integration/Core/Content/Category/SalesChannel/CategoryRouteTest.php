<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Content\Category\SalesChannel;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Category\CategoryDefinition;
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
class CategoryRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private KernelBrowser $browser;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
        static::getContainer()->get('category.repository')->create([[
            'id' => $this->ids->create('home'),
            'name' => 'Home',
            'type' => CategoryDefinition::TYPE_FOLDER,
            'children' => [
                ['id' => $this->ids->create('category'), 'name' => 'Products', 'type' => CategoryDefinition::TYPE_PAGE],
                ['id' => $this->ids->create('folder'), 'name' => 'Folder', 'type' => CategoryDefinition::TYPE_FOLDER],
                ['id' => $this->ids->create('link'), 'name' => 'Link', 'type' => CategoryDefinition::TYPE_LINK, 'linkType' => CategoryDefinition::LINK_TYPE_EXTERNAL, 'externalLink' => 'https://example.com'],
            ],
        ]], Context::createDefaultContext());
        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
            'navigationCategoryId' => $this->ids->get('home'),
        ]);
    }

    public function testLoadsCategoryData(): void
    {
        $this->browser->request('GET', '/store-api/category/' . $this->ids->get('category'));
        static::assertSame(200, $this->browser->getResponse()->getStatusCode());
        $response = $this->decodeResponse();
        static::assertSame($this->ids->get('category'), $response['id']);
        static::assertSame('Products', $response['name']);
        static::assertArrayNotHasKey('cmsPage', $response);
    }

    public function testHomeResolvesNavigationCategory(): void
    {
        $this->browser->request('POST', '/store-api/category/home');
        static::assertSame(200, $this->browser->getResponse()->getStatusCode());
        static::assertSame($this->ids->get('home'), $this->decodeResponse()['id']);
    }

    public function testFolderIsNotAContentPage(): void
    {
        $this->browser->request('GET', '/store-api/category/' . $this->ids->get('folder'));
        static::assertSame(404, $this->browser->getResponse()->getStatusCode());
    }

    public function testLinkCategoryKeepsItsDestination(): void
    {
        $this->browser->request('GET', '/store-api/category/' . $this->ids->get('link'));
        static::assertSame(200, $this->browser->getResponse()->getStatusCode());
        static::assertSame('https://example.com', $this->decodeResponse()['externalLink']);
    }

    public function testMissingCategoryReturnsNotFound(): void
    {
        $this->browser->request('GET', '/store-api/category/' . $this->ids->create('missing'));
        static::assertSame(404, $this->browser->getResponse()->getStatusCode());
    }

    public function testIncludesSelectResponseFields(): void
    {
        $this->browser->request('POST', '/store-api/category/' . $this->ids->get('category'), ['includes' => ['category' => ['id', 'name']]]);
        static::assertSame(200, $this->browser->getResponse()->getStatusCode());
        $response = $this->decodeResponse();
        static::assertSame($this->ids->get('category'), $response['id']);
        static::assertSame('Products', $response['name']);
        static::assertArrayNotHasKey('parentId', $response);
    }

    public function testCompressedCriteriaIsReadLikeRequestBody(): void
    {
        $url = '/store-api/category/' . $this->ids->get('category');
        $parameters = ['includes' => ['category' => ['id', 'name']]];
        $this->browser->request('POST', $url, $parameters);
        $expected = $this->decodeResponse();
        $compressed = gzencode(json_encode($parameters, \JSON_THROW_ON_ERROR));
        static::assertNotFalse($compressed);
        $this->browser->request('GET', $url, ['_criteria' => Base64::urlEncode($compressed)]);
        static::assertSame($expected, $this->decodeResponse());
    }

    public function testIncludesThatAreNotAnArrayAreRejected(): void
    {
        $this->browser->request('POST', '/store-api/category/' . $this->ids->get('category'), ['includes' => 'category']);
        static::assertSame(400, $this->browser->getResponse()->getStatusCode());
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
