<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\DependencyInjection\CompilerPass;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\DependencyInjection\CompilerPass\FilesystemConfigMigrationCompilerPass;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(FilesystemConfigMigrationCompilerPass::class)]
class FilesystemConfigMigrationCompilerPassTest extends TestCase
{
    private ContainerBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new ContainerBuilder();
        $this->builder->addCompilerPass(new FilesystemConfigMigrationCompilerPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 0);
        $this->builder->setParameter('shopwell.filesystem.public', []);
        $this->builder->setParameter('shopwell.filesystem.public.type', 'local');
        $this->builder->setParameter('shopwell.filesystem.public.config', []);
        $this->builder->setParameter('shopwell.cdn.url', 'http://test.de');
    }

    public function testConfigMigration(): void
    {
        $this->builder->compile();

        static::assertSame($this->builder->getParameter('shopwell.filesystem.public'), $this->builder->getParameter('shopwell.filesystem.theme'));
        static::assertSame($this->builder->getParameter('shopwell.filesystem.public'), $this->builder->getParameter('shopwell.filesystem.asset'));
        static::assertSame($this->builder->getParameter('shopwell.filesystem.public'), $this->builder->getParameter('shopwell.filesystem.sitemap'));

        static::assertSame($this->builder->getParameter('shopwell.filesystem.public.type'), $this->builder->getParameter('shopwell.filesystem.theme.type'));
        static::assertSame($this->builder->getParameter('shopwell.filesystem.public.type'), $this->builder->getParameter('shopwell.filesystem.asset.type'));
        static::assertSame($this->builder->getParameter('shopwell.filesystem.public.type'), $this->builder->getParameter('shopwell.filesystem.sitemap.type'));

        static::assertSame($this->builder->getParameter('shopwell.filesystem.public.config'), $this->builder->getParameter('shopwell.filesystem.theme.config'));
        static::assertSame($this->builder->getParameter('shopwell.filesystem.public.config'), $this->builder->getParameter('shopwell.filesystem.asset.config'));
        static::assertSame($this->builder->getParameter('shopwell.filesystem.public.config'), $this->builder->getParameter('shopwell.filesystem.sitemap.config'));

        // We cannot inherit them, cause they use always in 6.2 the shop url instead the configured one
        static::assertSame('', $this->builder->getParameter('shopwell.filesystem.theme.url'));
        static::assertSame('', $this->builder->getParameter('shopwell.filesystem.asset.url'));
        static::assertSame('', $this->builder->getParameter('shopwell.filesystem.sitemap.url'));

        static::assertTrue($this->builder->hasParameter('shopwell.filesystem.theme.visibility'));
    }

    public function testSetCustomConfigForTheme(): void
    {
        $this->builder->setParameter('shopwell.filesystem.theme', ['foo' => 'foo']);
        $this->builder->setParameter('shopwell.filesystem.theme.type', 'amazon-s3');
        $this->builder->setParameter('shopwell.filesystem.theme.config', ['test' => 'test']);
        $this->builder->setParameter('shopwell.filesystem.theme.url', 'http://cdn.de');

        $this->builder->compile();

        static::assertNotSame($this->builder->getParameter('shopwell.filesystem.public'), $this->builder->getParameter('shopwell.filesystem.theme'));
        static::assertNotSame($this->builder->getParameter('shopwell.filesystem.public.type'), $this->builder->getParameter('shopwell.filesystem.theme.type'));
        static::assertNotSame($this->builder->getParameter('shopwell.filesystem.public.config'), $this->builder->getParameter('shopwell.filesystem.theme.config'));

        static::assertSame('amazon-s3', $this->builder->getParameter('shopwell.filesystem.theme.type'));
        static::assertSame('http://cdn.de', $this->builder->getParameter('shopwell.filesystem.theme.url'));
        static::assertSame(['test' => 'test'], $this->builder->getParameter('shopwell.filesystem.theme.config'));
        static::assertTrue($this->builder->hasParameter('shopwell.filesystem.theme.visibility'));

        static::assertSame($this->builder->getParameter('shopwell.filesystem.public'), $this->builder->getParameter('shopwell.filesystem.asset'));
        static::assertSame($this->builder->getParameter('shopwell.filesystem.public.type'), $this->builder->getParameter('shopwell.filesystem.asset.type'));
        static::assertSame($this->builder->getParameter('shopwell.filesystem.public.config'), $this->builder->getParameter('shopwell.filesystem.asset.config'));

        static::assertSame($this->builder->getParameter('shopwell.filesystem.public'), $this->builder->getParameter('shopwell.filesystem.sitemap'));
        static::assertSame($this->builder->getParameter('shopwell.filesystem.public.type'), $this->builder->getParameter('shopwell.filesystem.sitemap.type'));
        static::assertSame($this->builder->getParameter('shopwell.filesystem.public.config'), $this->builder->getParameter('shopwell.filesystem.sitemap.config'));
    }
}
