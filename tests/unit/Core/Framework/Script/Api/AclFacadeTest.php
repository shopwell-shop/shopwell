<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Script\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Script\Api\AclFacade;
use Shopwell\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AclFacade::class)]
class AclFacadeTest extends TestCase
{
    public function testCan(): void
    {
        $source = new AdminApiSource(null, Uuid::randomHex());
        $source->setIsAdmin(false);
        $source->setPermissions(['product:read']);

        $context = Context::createCLIContext($source);

        $facade = new AclFacade($context);

        static::assertTrue($facade->can('product:read'));
        static::assertFalse($facade->can('order:read'));
    }
}
