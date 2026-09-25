<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Api\OAuth;

use Lcobucci\JWT\Signer\Hmac\Sha256 as Hmac256;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Api\OAuth\JWTConfigurationFactory;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(JWTConfigurationFactory::class)]
class JWTConfigurationFactoryTest extends TestCase
{
    public function testCreateFromAppEnv(): void
    {
        $config = JWTConfigurationFactory::createJWTConfiguration();

        static::assertInstanceOf(Hmac256::class, $config->signer());
    }
}
