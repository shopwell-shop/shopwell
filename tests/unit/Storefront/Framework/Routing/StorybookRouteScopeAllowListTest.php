<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Storefront\Framework\Routing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Storefront\Controller\StorybookController;
use Shopwell\Storefront\Framework\Routing\StorybookRouteScopeAllowList;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(StorybookRouteScopeAllowList::class)]
class StorybookRouteScopeAllowListTest extends TestCase
{
    private StorybookRouteScopeAllowList $allowList;

    protected function setUp(): void
    {
        $this->allowList = new StorybookRouteScopeAllowList();
    }

    public function testAppliesToStorybookController(): void
    {
        static::assertTrue($this->allowList->applies(StorybookController::class));
    }

    public function testDoesNotApplyToOtherControllers(): void
    {
        static::assertFalse($this->allowList->applies(\stdClass::class));
    }
}
