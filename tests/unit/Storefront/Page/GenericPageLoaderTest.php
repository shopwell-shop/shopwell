<?php

declare(strict_types=1);

namespace Shopwell\Tests\Unit\Storefront\Page;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\SalesChannelRequest;
use Shopwell\Core\System\SystemConfig\SystemConfigService;
use Shopwell\Core\Test\Generator;
use Shopwell\Storefront\Page\GenericPageLoader;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(GenericPageLoader::class)]
class GenericPageLoaderTest extends TestCase
{
    public function testLoad(): void
    {
        $systemConfigService = static::createStub(SystemConfigService::class);
        $systemConfigService->method('getString')->willReturn('Shopwell');

        $loader = new GenericPageLoader(
            $systemConfigService,
            static::createStub(EventDispatcherInterface::class)
        );

        $request = new Request(attributes: [SalesChannelRequest::ATTRIBUTE_DOMAIN_LOCALE => 'en-GB']);

        $metaInformation = $loader->load($request, Generator::generateSalesChannelContext())->getMetaInformation();
        static::assertNotNull($metaInformation);
        static::assertSame('Shopwell', $metaInformation->getMetaTitle());
        static::assertSame('en-GB', $metaInformation->getXmlLang());
    }
}
