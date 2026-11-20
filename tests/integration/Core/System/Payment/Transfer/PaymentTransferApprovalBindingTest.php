<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\System\Payment\Transfer;

use Doctrine\DBAL\Connection;
use Opis\JsonSchema\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\ApprovalFlow\Binding\ApprovalBindingService;
use Shopwell\Core\Framework\ApprovalFlow\Binding\ApprovalBindingStore;
use Shopwell\Core\Framework\ApprovalFlow\Binding\ApprovalBusinessTypeRegistry;
use Shopwell\Core\Framework\ApprovalFlow\Diagnostics\GraphDiagnostics;
use Shopwell\Core\Framework\ApprovalFlow\Execution\ApprovalReleaseLoader;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Payment\Api\Struct\PaymentTransferDraft;
use Shopwell\Core\System\Payment\Transfer\Approval\PaymentTransferApprovalException;
use Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Execution\Instance\_fixtures\ApprovalRuntimeFixture;
use Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Graph\_fixtures\GraphFixture;
use Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Publication\_fixtures\PublicationFixture;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @phpstan-import-type GraphPayload from GraphDiagnostics
 *
 * @internal
 */
#[Package('framework')]
class PaymentTransferApprovalBindingTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private Filesystem $filesystem;

    private string $directory;

    private ContainerBuilder $runtime;

    private ApprovalBindingService $bindings;

    protected function setUp(): void
    {
        $container = static::getContainer();
        $this->filesystem = new Filesystem();
        $this->directory = sys_get_temp_dir() . '/transfer-binding-' . Uuid::randomHex();
        $manifest = PublicationFixture::writeManifest($this->filesystem, $this->directory);
        $this->runtime = ApprovalRuntimeFixture::container($container, $manifest);
        $this->bindings = new ApprovalBindingService(
            $container->get(ApprovalBindingStore::class),
            $container->get(ApprovalBusinessTypeRegistry::class),
            $this->runtime->get(ApprovalReleaseLoader::class),
            $container->get('event_dispatcher'),
        );
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->directory);
    }

    public function testPaymentContributesItsBusinessTypeThroughTheRealContainer(): void
    {
        $business = static::getContainer()->get(ApprovalBusinessTypeRegistry::class)->get('payment:transfer', '1.0.0');
        $schema = json_decode($business->formSchema, associative: true, flags: \JSON_THROW_ON_ERROR);

        static::assertSame('payment:transfer', $business->formSchemaId);
        static::assertSame('payment:transfer.yaml', $business->source);
        static::assertSame('integer', $schema['properties']['amount']['type']);
        static::assertSame(1, $schema['properties']['amount']['minimum']);
        static::assertFalse($schema['additionalProperties']);
        static::assertSame(['amount', 'currency', 'recipient', 'paymentAccount', 'reason'], $schema['required']);
    }

    #[DataProvider('forms')]
    public function testTheDeclaredSchemaValidatesTransferFormValues(string $field, mixed $value, bool $valid): void
    {
        $business = static::getContainer()->get(ApprovalBusinessTypeRegistry::class)->get('payment:transfer', '1.0.0');
        $form = (object) ['amount' => 100, 'currency' => 'CNY', 'recipient' => 'Li ••••1234', 'paymentAccount' => 'Merchant account', 'reason' => null];
        $form->{$field} = $value;

        static::assertSame($valid, (new Validator())->validate($form, $business->formSchema)->isValid());
    }

    public static function forms(): iterable
    {
        yield 'maximum payment amount remains an exact integer' => ['amount', PaymentTransferDraft::MAX_AMOUNT, true];
        yield 'over the payment limit is rejected' => ['amount', PaymentTransferDraft::MAX_AMOUNT + 1, false];
        yield 'unsafe JavaScript integer is rejected' => ['amount', 9007199254740992, false];
        yield 'decimal minor units are rejected' => ['amount', 1.5, false];
        yield 'zero amount is rejected' => ['amount', 0, false];
        yield 'lowercase currency is rejected' => ['currency', 'cny', false];
        yield 'absent business reason is frozen as null' => ['reason', null, true];
        yield 'an unrecognized recipient identity field is rejected' => ['identityValue', 'private account handle', false];
    }

    public function testSavingAndActivatingARealTransferBindingSucceeds(): void
    {
        $context = Context::createDefaultContext();
        $releaseId = ApprovalRuntimeFixture::publish(static::getContainer(), $this->runtime, GraphFixture::json());
        $binding = $this->bindings->save('payment:transfer', '1.0.0', $releaseId, 0, $context);
        $head = $this->bindings->activate('payment:transfer', 1, 0, $context);

        static::assertSame($releaseId, $binding->getReleaseId());
        static::assertSame(1, $head->getActiveRevision());
    }

    public function testBusinessValidationRejectsSavingASkippableApproval(): void
    {
        /** @var GraphPayload $graph */
        $graph = json_decode(GraphFixture::json(), flags: \JSON_THROW_ON_ERROR);
        $graph->nodes[1]->config->emptyAssignees = 'skip';
        $releaseId = ApprovalRuntimeFixture::publish(static::getContainer(), $this->runtime, json_encode($graph, \JSON_THROW_ON_ERROR));
        try {
            $this->bindings->save('payment:transfer', '1.0.0', $releaseId, 0, Context::createDefaultContext());
            static::fail('A transfer binding must not bypass approval.');
        } catch (PaymentTransferApprovalException $exception) {
            static::assertSame('approval_must_not_skip', $exception->getParameter('reason'));
        }

        static::assertSame(0, (int) static::getContainer()->get(Connection::class)->fetchOne('SELECT COUNT(*) FROM approval_flow_binding_head'));
    }

    public function testActivationRechecksBusinessRulesAndKeepsThePreviousSelection(): void
    {
        $context = Context::createDefaultContext();
        $container = static::getContainer();
        $first = $this->bindings->save('payment:transfer', '1.0.0', ApprovalRuntimeFixture::publish($container, $this->runtime, GraphFixture::json()), 0, $context);
        $this->bindings->activate('payment:transfer', 1, 0, $context);
        /** @var GraphPayload $graph */
        $graph = json_decode(GraphFixture::json(), flags: \JSON_THROW_ON_ERROR);
        $graph->nodes[1]->config->emptyAssignees = 'skip';
        $releaseId = ApprovalRuntimeFixture::publish($container, $this->runtime, json_encode($graph, \JSON_THROW_ON_ERROR));
        // A previously saved revision can predate a newly installed business rule.
        $business = $container->get(ApprovalBusinessTypeRegistry::class)->get('payment:transfer', '1.0.0');
        $container->get(ApprovalBindingStore::class)->save($business, $releaseId, 1, $context, static function (): void {});
        try {
            $this->bindings->activate('payment:transfer', 2, 1, $context);
            static::fail('Activation must recheck the business rule.');
        } catch (PaymentTransferApprovalException $exception) {
            static::assertSame('approval_must_not_skip', $exception->getParameter('reason'));
        }

        static::assertSame(1, $this->bindings->head('payment:transfer', $context)->getVersion());
        static::assertSame($first->getId(), $this->bindings->active('payment:transfer', $context)->getId());
    }
}
