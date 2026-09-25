<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\LandingPage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\LandingPage\LandingPageEntity;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(LandingPageEntity::class)]
class LandingPageEntityTest extends TestCase
{
    public function testSlotConfigCanBeSet(): void
    {
        $landingPage = new LandingPageEntity();
        $landingPage->setSlotConfig(['slot-id' => ['content' => ['source' => 'static']]]);

        static::assertSame(['slot-id' => ['content' => ['source' => 'static']]], $landingPage->getSlotConfig());
    }

    public function testANullSlotConfigIsRejected(): void
    {
        $landingPage = new LandingPageEntity();

        $this->expectException(\Throwable::class);

        $landingPage->setSlotConfig(null);
    }
}
