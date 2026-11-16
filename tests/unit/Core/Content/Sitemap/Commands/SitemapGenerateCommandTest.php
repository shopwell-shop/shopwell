<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Sitemap\Commands;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Sitemap\Commands\SitemapGenerateCommand;
use Shopwell\Core\Content\Sitemap\Service\SitemapExporterInterface;
use Shopwell\Core\Content\Sitemap\Service\SitemapSalesChannelLoader;
use Shopwell\Core\Content\Sitemap\Struct\SitemapGenerationResult;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainCollection;
use Shopwell\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopwell\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopwell\Core\System\SalesChannel\SalesChannelCollection;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\System\SalesChannel\SalesChannelEntity;
use Shopwell\Core\Test\Generator;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(SitemapGenerateCommand::class)]
class SitemapGenerateCommandTest extends TestCase
{
    public function testAFailingSalesChannelDoesNotStopTheGenerationOfTheOthers(): void
    {
        $generatedFor = [];
        $exporter = static::createStub(SitemapExporterInterface::class);
        $exporter->method('generate')->willReturnCallback(
            static function (SalesChannelContext $context) use (&$generatedFor): SitemapGenerationResult {
                $generatedFor[] = $context->getSalesChannelId();
                if ($context->getSalesChannelId() === 'failing-sales-channel') {
                    throw new \RuntimeException('Provider failed');
                }

                return new SitemapGenerationResult(true, null, null, $context->getSalesChannelId(), $context->getLanguageId());
            }
        );

        $loader = new SitemapSalesChannelLoader(
            new StaticEntityRepository([new SalesChannelCollection([
                $this->createSalesChannel('first-sales-channel'),
                $this->createSalesChannel('failing-sales-channel'),
                $this->createSalesChannel('last-sales-channel'),
            ])]),
            new EventDispatcher()
        );

        $tester = new CommandTester(new SitemapGenerateCommand($loader, $exporter, $this->createContextFactory()));
        $status = $tester->execute([]);

        static::assertSame(['first-sales-channel', 'failing-sales-channel', 'last-sales-channel'], $generatedFor);
        static::assertSame(Command::FAILURE, $status);
        static::assertStringContainsString('ERROR: Provider failed', $tester->getDisplay());
        static::assertStringContainsString('Sitemap generation failed for 1 sales channel language(s)', $tester->getDisplay());
    }

    public function testSucceedsWhenAllSalesChannelsAreGenerated(): void
    {
        $exporter = static::createStub(SitemapExporterInterface::class);
        $exporter->method('generate')->willReturnCallback(
            static fn (SalesChannelContext $context): SitemapGenerationResult => new SitemapGenerationResult(true, null, null, $context->getSalesChannelId(), $context->getLanguageId())
        );

        $loader = new SitemapSalesChannelLoader(
            new StaticEntityRepository([new SalesChannelCollection([$this->createSalesChannel('sales-channel')])]),
            new EventDispatcher()
        );

        $tester = new CommandTester(new SitemapGenerateCommand($loader, $exporter, $this->createContextFactory()));

        static::assertSame(Command::SUCCESS, $tester->execute([]));
        static::assertStringContainsString('done!', $tester->getDisplay());
    }

    private function createContextFactory(): AbstractSalesChannelContextFactory
    {
        $contextFactory = static::createStub(AbstractSalesChannelContextFactory::class);
        $contextFactory->method('create')->willReturnCallback(
            static function (string $token, string $salesChannelId): SalesChannelContext {
                $salesChannel = new SalesChannelEntity();
                $salesChannel->setId($salesChannelId);

                return Generator::generateSalesChannelContext(salesChannel: $salesChannel);
            }
        );

        return $contextFactory;
    }

    private function createSalesChannel(string $id): SalesChannelEntity
    {
        $domain = new SalesChannelDomainEntity();
        $domain->setId($id . '-domain');
        $domain->setLanguageId(Defaults::LANGUAGE_SYSTEM);

        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId($id);
        $salesChannel->setName($id);
        $salesChannel->setTypeId(Defaults::SALES_CHANNEL_TYPE_STOREFRONT);
        $salesChannel->setDomains(new SalesChannelDomainCollection([$domain]));

        return $salesChannel;
    }
}
