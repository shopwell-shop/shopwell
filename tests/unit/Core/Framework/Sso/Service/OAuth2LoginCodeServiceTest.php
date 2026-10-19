<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Sso\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Sso\Service\OAuth2LoginCodeService;
use Shopwell\Core\Framework\Sso\SsoException;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(OAuth2LoginCodeService::class)]
class OAuth2LoginCodeServiceTest extends TestCase
{
    public function testConsumeReturnsTheUserOfTheCode(): void
    {
        $service = new OAuth2LoginCodeService(new ArrayAdapter(), new MockClock());

        $code = $service->create('user-id');

        static::assertSame('user-id', $service->consume($code));
    }

    public function testACodeCanOnlyBeUsedOnce(): void
    {
        $service = new OAuth2LoginCodeService(new ArrayAdapter(), new MockClock());

        $code = $service->create('user-id');
        $service->consume($code);

        $this->expectExceptionObject(SsoException::oauth2InvalidLoginCode());

        $service->consume($code);
    }

    public function testConsumeThrowsForAnUnknownCode(): void
    {
        $service = new OAuth2LoginCodeService(new ArrayAdapter(), new MockClock());

        $this->expectExceptionObject(SsoException::oauth2InvalidLoginCode());

        $service->consume('not-a-code-of-this-shop');
    }

    public function testConsumeThrowsForAnExpiredCode(): void
    {
        $clock = new MockClock();
        $service = new OAuth2LoginCodeService(new ArrayAdapter(), $clock);

        $code = $service->create('user-id');
        $clock->modify('+2 minutes');

        $this->expectExceptionObject(SsoException::oauth2InvalidLoginCode());

        $service->consume($code);
    }

    public function testCreatedCodesAreNotGuessable(): void
    {
        $service = new OAuth2LoginCodeService(new ArrayAdapter(), new MockClock());

        static::assertNotSame($service->create('user-id'), $service->create('user-id'));
    }
}
