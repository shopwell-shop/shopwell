<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Account\Api;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Account\Api\PaymentAccountController;
use Shopwell\Core\System\Payment\Account\PaymentAccountCollection;
use Shopwell\Core\System\Payment\Account\PaymentAccountLock;
use Shopwell\Core\System\Payment\Account\PaymentAccountService;
use Shopwell\Core\System\Payment\App\PaymentAppCollection;
use Shopwell\Core\System\Payment\App\PaymentCredentialCipher;
use Shopwell\Core\System\Payment\Exception\PaymentProviderException;
use Shopwell\Core\System\Payment\Provider\Config\PaymentProviderConfigCollection;
use Shopwell\Core\System\Payment\Provider\PaymentProviderRegistry;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentAccountController::class)]
class PaymentAccountControllerTest extends TestCase
{
    public function testAnEmptyAccountListUsesTheDocumentedEnvelope(): void
    {
        $response = $this->controller()->list(Context::createDefaultContext());
        static::assertSame(200, $response->getStatusCode());
        static::assertSame(['accounts' => []], json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR));
    }

    public function testReadingAnUnknownAccountPreservesTheDomainNotFoundError(): void
    {
        $this->expectExceptionObject(PaymentProviderException::accountNotFound('missing'));
        $this->controller()->read('missing', Context::createDefaultContext());
    }

    private function controller(): PaymentAccountController
    {
        return new PaymentAccountController(new PaymentAccountService(
            new StaticEntityRepository([new PaymentAccountCollection()]),
            new StaticEntityRepository([new PaymentAppCollection()]),
            new StaticEntityRepository([new PaymentProviderConfigCollection()]),
            new PaymentProviderRegistry([]),
            new PaymentCredentialCipher('test-key'),
            static::createStub(Connection::class),
            static::createStub(PaymentAccountLock::class),
        ));
    }
}
