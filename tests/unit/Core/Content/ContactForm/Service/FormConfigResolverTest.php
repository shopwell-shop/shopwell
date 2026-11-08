<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\ContactForm\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\ContactForm\Service\FormConfigResolver;
use Shopwell\Core\Test\Generator;
use Shopwell\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;

/**
 * @internal
 */
#[CoversClass(FormConfigResolver::class)]
class FormConfigResolverTest extends TestCase
{
    public function testIndependentFormUsesSalesChannelMailRecipient(): void
    {
        $context = Generator::generateSalesChannelContext();
        $config = new StaticSystemConfigService();
        $config->set('core.basicInformation.email', 'owner@example.com', $context->getSalesChannelId());
        $config->set('core.basicInformation.shopName', 'Example shop', $context->getSalesChannelId());

        static::assertSame([
            'receivers' => ['owner@example.com' => 'Example shop'],
            'message' => '',
        ], (new FormConfigResolver($config))->resolve($context));
    }
}
