<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Customer\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Customer\Extension\LoginByCredentialsExtension;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Test\Generator;
use Shopwell\Tests\Examples\LoginByCredentialsExample;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(LoginByCredentialsExtension::class)]
class LoginByCredentialsExtensionTest extends TestCase
{
    public function testSubscriberResolvesLogin(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new LoginByCredentialsExample());

        $coreCalled = false;
        $result = (new ExtensionDispatcher($dispatcher))->publish(
            name: LoginByCredentialsExtension::NAME,
            extension: new LoginByCredentialsExtension(
                'user@example.com',
                'secret',
                Generator::generateSalesChannelContext(),
            ),
            function: static function () use (&$coreCalled): string {
                $coreCalled = true;

                return 'core-token';
            },
        );

        static::assertFalse($coreCalled, 'The core login must be skipped when a subscriber resolves it.');
        static::assertSame('your-context-token', $result);
    }
}
