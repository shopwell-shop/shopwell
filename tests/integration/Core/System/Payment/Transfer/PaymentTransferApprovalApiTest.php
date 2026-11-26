<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\System\Payment\Transfer;

use Opis\JsonSchema\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('framework')]
class PaymentTransferApprovalApiTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    public function testReapplicationRequiresThePrivilegeToCreateATransfer(): void
    {
        $browser = $this->getBrowser(permissions: ['payment_transfer:read']);
        $browser->jsonRequest('POST', '/api/_action/payment/transfers/' . Uuid::randomHex() . '/reapply', []);
        static::assertSame(403, $browser->getResponse()->getStatusCode());
    }

    public function testBatchRequiresFinanceReadPrivilege(): void
    {
        $browser = $this->getBrowser(permissions: ['approval_flow:access']);
        $browser->jsonRequest('POST', '/api/_action/payment/transfers/approvals', ['ids' => []]);
        static::assertSame(403, $browser->getResponse()->getStatusCode());
    }

    public function testEmptyBatchProducesAnEmptyObject(): void
    {
        $browser = $this->getBrowser(permissions: ['payment_transfer:read']);
        $browser->jsonRequest('POST', '/api/_action/payment/transfers/approvals', ['ids' => []]);
        static::assertSame(200, $browser->getResponse()->getStatusCode(), (string) $browser->getResponse()->getContent());
        static::assertSame('{"approvals":{}}', $browser->getResponse()->getContent());
    }

    public function testIdentifierCaseVariantsAreDeduplicatedAndTheResponseMatchesItsSchema(): void
    {
        $browser = $this->getBrowser(permissions: ['payment_transfer:read']);
        $id = Uuid::randomHex();
        $browser->jsonRequest('POST', '/api/_action/payment/transfers/approvals', ['ids' => [$id, strtoupper($id)]]);
        static::assertSame(200, $browser->getResponse()->getStatusCode());
        $content = (string) $browser->getResponse()->getContent();
        $body = json_decode($content, true, flags: \JSON_THROW_ON_ERROR);
        static::assertSame([$id => ['status' => 'not_started', 'currentAssignees' => [], 'detailRoute' => null]], $body['approvals']);
        $path = \dirname(__DIR__, 6) . '/src/Core/Framework/Api/ApiDefinition/Generator/Schema/AdminApi/paths/payment-transfer.json';
        $definition = json_decode((string) file_get_contents($path), true, flags: \JSON_THROW_ON_ERROR);
        $schema = json_decode(json_encode($definition['paths']['/_action/payment/transfers/approvals']['post']['responses']['200']['content']['application/json']['schema'], \JSON_THROW_ON_ERROR), flags: \JSON_THROW_ON_ERROR);
        static::assertInstanceOf(\stdClass::class, $schema);
        static::assertTrue((new Validator())->validate(json_decode($content, flags: \JSON_THROW_ON_ERROR), $schema)->isValid());
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[DataProvider('invalidBatches')]
    public function testInvalidBatchIsRejected(array $payload): void
    {
        $browser = $this->getBrowser(permissions: ['payment_transfer:read']);
        $browser->jsonRequest('POST', '/api/_action/payment/transfers/approvals', $payload);
        static::assertSame(400, $browser->getResponse()->getStatusCode());
    }

    public static function invalidBatches(): \Generator
    {
        yield 'missing ids' => [[]];
        yield 'string instead of list' => [['ids' => 'all']];
        yield 'invalid identifier' => [['ids' => ['invalid']]];
        yield 'non-string identifier' => [['ids' => [123]]];
        yield 'keyed object instead of list' => [['ids' => ['arbitrary' => Uuid::randomHex()]]];
        yield 'page exceeds one hundred records' => [['ids' => array_fill(0, 101, Uuid::randomHex())]];
    }
}
