<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Storefront\Framework\HealthCheck\Util;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Cart\CartRuleLoader;
use Shopwell\Core\Checkout\Cart\Event\CartCreatedEvent;
use Shopwell\Core\Checkout\Cart\Extension\CheckoutCartRuleLoaderExtension;
use Shopwell\Core\Checkout\Cart\LineItem\LineItem;
use Shopwell\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopwell\Core\Checkout\Cart\Rule\LineItemsInCartCountRule;
use Shopwell\Core\Checkout\Cart\RuleLoaderResult;
use Shopwell\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopwell\Core\Test\Integration\EventDispatcher\EventHookDispatcher;
use Shopwell\Core\Test\Stub\Framework\IdsCollection;
use Shopwell\Storefront\Framework\SystemCheck\Util\SalesChannelDomain;
use Shopwell\Storefront\Framework\SystemCheck\Util\SalesChannelDomainContextFactory;

/**
 * @internal
 */
#[Package('discovery')]
class SalesChannelDomainContextFactoryTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private IdsCollection $ids;

    private SalesChannelDomain $domain;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
        $this->createSalesChannel([
            'id' => $this->ids->create('sales-channel'),
            'domains' => [
                [
                    'id' => $this->ids->create('domain'),
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'currencyId' => Defaults::CURRENCY,
                    'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                    'url' => 'http://domain-context.test',
                ],
            ],
        ]);

        $this->domain = SalesChannelDomain::create(
            $this->ids->get('sales-channel'),
            'http://domain-context.test',
            $this->ids->get('domain'),
            Defaults::LANGUAGE_SYSTEM,
            Defaults::CURRENCY,
        );
    }

    /**
     * The probe request creates its new cart through the CartFactory, so an extension seeding new carts via
     * the CartCreatedEvent changes which rules match for it. The lookup has to see the same rules.
     */
    public function testRulesMatchingACartSeededOnCreationAreDetected(): void
    {
        static::getContainer()->get('rule.repository')->create([[
            'id' => $this->ids->create('rule'),
            'name' => 'cart is not empty',
            'priority' => 1,
            'conditions' => [[
                'type' => LineItemsInCartCountRule::RULE_NAME,
                'value' => ['operator' => '>=', 'count' => 1],
            ]],
        ]], Context::createDefaultContext());
        static::getContainer()->get(CartRuleLoader::class)->invalidate();

        EventHookDispatcher::fromContainer(static::getContainer())->on(
            CartCreatedEvent::class,
            static function (CartCreatedEvent $event): void {
                $lineItem = new LineItem('seeded-by-an-extension', LineItem::CUSTOM_LINE_ITEM_TYPE);
                $lineItem->setLabel('seeded');
                $lineItem->setPriceDefinition(new QuantityPriceDefinition(10.0, new TaxRuleCollection()));

                $event->getCart()->add($lineItem);
            }
        );

        $context = static::getContainer()->get(SalesChannelDomainContextFactory::class)->create($this->domain);

        static::assertContains($this->ids->get('rule'), $context->getRuleIds());
    }

    /**
     * The cart is only calculated to detect the rules. A cart processor or extension adding a line item
     * would make the rule loader store it, under a random token nobody ever uses again, so every run of a
     * readiness check would leave an orphaned cart behind.
     */
    public function testTheRuleMatchingCartIsNotStoredEvenWhenItChanges(): void
    {
        $cartChanged = false;
        EventHookDispatcher::fromContainer(static::getContainer())->on(
            ExtensionDispatcher::post(CheckoutCartRuleLoaderExtension::NAME),
            static function (CheckoutCartRuleLoaderExtension $extension) use (&$cartChanged): void {
                if (!$extension->result instanceof RuleLoaderResult) {
                    return;
                }

                $extension->result->getCart()->add(new LineItem('added-by-an-extension', LineItem::CUSTOM_LINE_ITEM_TYPE));
                $cartChanged = true;
            }
        );

        $context = static::getContainer()->get(SalesChannelDomainContextFactory::class)->create($this->domain);

        static::assertTrue($cartChanged);

        $storedCarts = static::getContainer()->get(Connection::class)
            ->fetchOne('SELECT COUNT(*) FROM `cart` WHERE `token` = :token', ['token' => $context->getToken()]);

        static::assertSame(0, (int) $storedCarts);
    }
}
