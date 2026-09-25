<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\App;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\App\AppHandlerIdentifier;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AppHandlerIdentifier::class)]
class AppHandlerIdentifierTest extends TestCase
{
    public function testReturnsPrefix(): void
    {
        static::assertSame('app\\', AppHandlerIdentifier::prefix());
    }

    public function testBuildsIdentifier(): void
    {
        static::assertSame('app\\ExampleApp_payment', AppHandlerIdentifier::build('ExampleApp', 'payment'));
    }
}
