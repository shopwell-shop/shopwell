<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Content\Sitemap\Service;

use League\Flysystem\Filesystem;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Category\CategoryEntity;
use Shopwell\Core\Content\Sitemap\Service\SitemapHandle;
use Shopwell\Core\Content\Sitemap\Struct\Url;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('discovery')]
class SitemapHandleTest extends TestCase
{
    use KernelTestBehaviour;

    private ?SitemapHandle $handle = null;

    public function testWriteWithoutFinish(): void
    {
        $url = new Url();
        $url->setLoc('https://shopwell.cn');
        $url->setLastmod(new \DateTime());
        $url->setChangefreq('weekly');
        $url->setResource(CategoryEntity::class);
        $url->setIdentifier(Uuid::randomHex());

        $fileSystem = $this->createMock(Filesystem::class);
        $fileSystem->expects($this->never())->method('write');

        $this->handle = new SitemapHandle(
            $fileSystem,
            $this->getContext(),
            static::getContainer()->get('event_dispatcher')
        );

        $this->handle->write([
            $url,
        ]);
    }

    public function testWrite(): void
    {
        $url = new Url();
        $url->setLoc('https://shopwell.cn');
        $url->setLastmod(new \DateTime());
        $url->setChangefreq('weekly');
        $url->setResource(CategoryEntity::class);
        $url->setIdentifier(Uuid::randomHex());

        $fileSystem = $this->createMock(Filesystem::class);
        $fileSystem->expects($this->once())->method('write');

        $this->handle = new SitemapHandle(
            $fileSystem,
            $this->getContext(),
            static::getContainer()->get('event_dispatcher')
        );

        $this->handle->write([$url]);
        $this->handle->finish();
    }

    public function testWrite101kItems(): void
    {
        $url = new Url();
        $url->setLoc('https://shopwell.cn');
        $url->setLastmod(new \DateTime());
        $url->setChangefreq('weekly');
        $url->setResource(CategoryEntity::class);
        $url->setIdentifier(Uuid::randomHex());

        $list = [];

        for ($i = 1; $i <= 101000; ++$i) {
            $list[] = clone $url;
        }

        $fileSystem = $this->createMock(Filesystem::class);
        $fileSystem->expects($this->atLeast(3))->method('write');

        $this->handle = new SitemapHandle(
            $fileSystem,
            $this->getContext(),
            static::getContainer()->get('event_dispatcher')
        );

        $this->handle->write($list);
        $this->handle->finish();
    }

    private function getContext(): SalesChannelContext
    {
        return static::createStub(SalesChannelContext::class);
    }
}
