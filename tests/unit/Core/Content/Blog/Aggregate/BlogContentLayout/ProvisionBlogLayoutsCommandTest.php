<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Blog\Aggregate\BlogContentLayout;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Blog\Aggregate\BlogContentLayout\ProvisionBlogLayoutsCommand;
use Shopwell\Core\Framework\ContentSystem\Layout\Preset\ContentSystemLayoutProvisioner;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(ProvisionBlogLayoutsCommand::class)]
class ProvisionBlogLayoutsCommandTest extends TestCase
{
    public function testProvisioningRetainsExistingDefaultsAndCreatesOnlyMissingType(): void
    {
        $config = new StaticSystemConfigService();
        $config->set('core.content_system.default_blog_post_content_layout', 'custom-post-layout');
        $config->set('core.content_system.default_blog_listing_content_layout', 'custom-listing-layout');
        $provisioner = $this->createMock(ContentSystemLayoutProvisioner::class);
        $provisioner->expects($this->once())->method('provisionFromPreset')->with('Blog video', 'Sw:BlogVideo', 'blog', static::anything())->willReturn('video-layout');
        $tester = new CommandTester(new ProvisionBlogLayoutsCommand($provisioner, $config));

        static::assertSame(0, $tester->execute([]));
        static::assertSame('custom-post-layout', $config->getString('core.content_system.default_blog_post_content_layout'));
        static::assertSame('video-layout', $config->getString('core.content_system.default_blog_video_content_layout'));
    }
}
