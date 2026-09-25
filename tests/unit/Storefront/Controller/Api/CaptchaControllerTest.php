<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Storefront\Controller\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Storefront\Controller\Api\CaptchaController;
use Shopwell\Storefront\Framework\Captcha\AbstractCaptcha;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(CaptchaController::class)]
class CaptchaControllerTest extends TestCase
{
    private const CAPTCHA_NAME = 'lorem-ipsum';

    private CaptchaController $captchaController;

    protected function setUp(): void
    {
        $captchaMock = static::createStub(AbstractCaptcha::class);
        $captchaMock->method('getName')->willReturn(self::CAPTCHA_NAME);

        $this->captchaController = new CaptchaController([$captchaMock]);
    }

    public function testList(): void
    {
        $expected = json_encode([
            self::CAPTCHA_NAME,
        ]);

        static::assertIsString($expected);

        $response = $this->captchaController->list();

        static::assertJsonStringEqualsJsonString($expected, $response->getContent() ?: '');
    }
}
