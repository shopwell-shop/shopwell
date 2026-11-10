<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\System\Sms\Gateway;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Sms\Gateway\SmsGatewayCollection;
use Shopwell\Core\System\Sms\Gateway\SmsGatewayEntity;
use Shopwell\Core\System\Sms\SmsException;

/**
 * @internal
 */
#[Package('framework')]
class SmsGatewayValidatorTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    /**
     * @var EntityRepository<SmsGatewayCollection>
     */
    private EntityRepository $gateways;

    protected function setUp(): void
    {
        $this->gateways = static::getContainer()->get('sms_gateway.repository');
    }

    public function testRejectsAnEnabledGatewayWithBlankCredentialsThroughTheRepository(): void
    {
        $this->expectExceptionObject(SmsException::configurationInvalid('aliyun', ['credentials.accessKeySecret']));

        $this->gateways->create([[
            'technicalName' => 'aliyun', 'enabled' => true, 'priority' => 0,
            'credentials' => ['accessKeyId' => 'key', 'accessKeySecret' => ''],
        ]], Context::createDefaultContext());
    }

    public function testValidatesTheStoredCredentialsWhenEnablingAPartiallyConfiguredGateway(): void
    {
        $id = Uuid::randomHex();
        $context = Context::createDefaultContext();
        $this->gateways->create([[
            'id' => $id, 'technicalName' => 'aliyun', 'enabled' => false, 'priority' => 0,
            'credentials' => ['accessKeyId' => 'key'],
        ]], $context);

        $this->expectExceptionObject(SmsException::configurationInvalid('aliyun', ['credentials.accessKeySecret']));

        $this->gateways->update([['id' => $id, 'enabled' => true]], $context);
    }

    public function testPersistsAValidConfigurationAndPreservesOptionsOnPartialUpdate(): void
    {
        $id = Uuid::randomHex();
        $context = Context::createDefaultContext();
        $this->gateways->create([[
            'id' => $id, 'technicalName' => 'aliyun', 'enabled' => true, 'priority' => 0,
            'credentials' => ['accessKeyId' => 'key', 'accessKeySecret' => 'secret'],
            'options' => ['signName' => 'Shop', 'extensionOption' => 'retained'],
        ]], $context);
        $this->gateways->update([['id' => $id, 'label' => 'Renamed account']], $context);

        $gateway = $this->gateways->search(new Criteria([$id]), $context)->first();

        static::assertInstanceOf(SmsGatewayEntity::class, $gateway);
        static::assertSame('Renamed account', $gateway->label);
        static::assertSame(['signName' => 'Shop', 'extensionOption' => 'retained'], $gateway->options);
    }
}
