<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Transfer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\DataAbstractionLayer\AttributeEntityCompiler;
use Shopwell\Core\Framework\DataAbstractionLayer\AttributeEntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferCollection;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferEntity;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferStates;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentTransferEntity::class)]
class PaymentTransferEntityTest extends TestCase
{
    #[DataProvider('names')]
    public function testTheNamesAreStable(string $expected, string $actual): void
    {
        static::assertSame($expected, $actual);
    }

    /**
     * @return \Generator<string, array{string, string}>
     */
    public static function names(): \Generator
    {
        yield 'entity name' => ['payment_transfer', PaymentTransferEntity::ENTITY_NAME];
        yield 'api alias' => ['payment_transfer_collection', (new PaymentTransferCollection())->getApiAlias()];
        yield 'state machine' => ['payment_transfer.status', PaymentTransferStates::STATE_MACHINE];
    }

    /**
     * The definition is what the dal reads - a property that does not make it into it does not exist
     * for the repository, so it is built here the way the compiler pass builds it.
     */
    public function testTheDefinitionDescribesTheEntity(): void
    {
        $definition = $this->definition();

        static::assertSame(PaymentTransferEntity::ENTITY_NAME, $definition->getEntityName());
        static::assertSame(PaymentTransferCollection::class, $definition->getCollectionClass());
        static::assertSame(PaymentTransferEntity::class, $definition->getEntityClass());
        static::assertTrue($definition->hasCreatedAndUpdatedAtFields());
    }

    /**
     * These are the columns a writer has to spell out: every one of them is not nullable and none of
     * them carries a ddl default, so the dal would reject an insert without them - `submitAttempts`
     * in particular, because a property initial value is never written. `id` and `createdAt` are the
     * two the dal fills itself.
     */
    public function testTheRequiredFieldsAreTheColumnsTheWriterSpellsOut(): void
    {
        $required = [];
        foreach ($this->definition()->getFields() as $field) {
            if ($field->is(Required::class)) {
                $required[] = $field->getPropertyName();
            }
        }

        sort($required);

        static::assertSame([
            'amount',
            'channel',
            'createdAt',
            'currency',
            'id',
            'outNo',
            'paymentAppId',
            'provider',
            'recipientId',
            'statusId',
            'submitAttempts',
            'transferNo',
        ], $required);
    }

    /**
     * The state field and the association to the state machine read the very same column - without
     * the association the schema builder never sees the relation and every migration diff would
     * propose to drop the foreign key.
     */
    public function testTheStateMachineAssociationReadsTheStatusColumn(): void
    {
        $fields = $this->definition()->getFields();

        static::assertTrue($fields->has('statusId'));
        static::assertTrue($fields->has('statusMachineState'));

        $association = $fields->get('statusMachineState');
        static::assertInstanceOf(ManyToOneAssociationField::class, $association);
        static::assertSame('status_id', $association->getStorageName());
    }

    public function testTheDefinitionDeclaresNoDefaults(): void
    {
        static::assertSame([], $this->definition()->getDefaults());
    }

    public function testPropertiesRoundTrip(): void
    {
        $entity = new PaymentTransferEntity();
        $entity->id = Uuid::randomHex();
        $entity->paymentAppId = Uuid::randomHex();
        $entity->transferNo = 'PT1A2B3C4D';
        $entity->outNo = 'commission-2026-10-0001';
        $entity->recipientId = Uuid::randomHex();
        $entity->provider = 'stripe';
        $entity->channel = 'stripe_payout';
        $entity->amount = 1990;
        $entity->currency = 'CNY';
        $entity->statusId = Uuid::randomHex();
        $entity->providerStageRef = 'tr_1A2B3C4D';
        $entity->confirmPayload = ['package' => 'MchId=1'];
        $entity->failureCode = 'account_closed';
        $entity->expireAt = new \DateTimeImmutable('2026-10-19 09:00:00');
        $entity->settledAt = new \DateTimeImmutable('2026-10-18 09:00:00');

        static::assertSame('PT1A2B3C4D', $entity->transferNo);
        static::assertSame('commission-2026-10-0001', $entity->outNo);
        static::assertSame(1990, $entity->amount);
        static::assertSame('CNY', $entity->currency);
        static::assertSame('tr_1A2B3C4D', $entity->providerStageRef);
        static::assertSame(['package' => 'MchId=1'], $entity->confirmPayload);
        static::assertSame('account_closed', $entity->failureCode);
        static::assertSame('2026-10-19 09:00:00', $entity->expireAt->format('Y-m-d H:i:s'));
        static::assertSame('2026-10-18 09:00:00', $entity->settledAt->format('Y-m-d H:i:s'));
        static::assertNull($entity->providerRef);
        static::assertNull($entity->returnedAt);
        static::assertNull($entity->canceledAt);
    }

    /**
     * The counter is not written by the dal - every writer spells it out, exactly like the paid amount
     * of a payment order. The property initial value only decides what an in-memory entity starts as.
     */
    public function testSubmitAttemptsDefaultsToZero(): void
    {
        static::assertSame(0, (new PaymentTransferEntity())->submitAttempts);
    }

    public function testCollectionHoldsEntitiesById(): void
    {
        $entity = new PaymentTransferEntity();
        $id = Uuid::randomHex();
        $entity->id = $id;
        $entity->transferNo = 'PTZZZZZZZZ';

        $collection = new PaymentTransferCollection();
        $collection->set($id, $entity);

        static::assertSame($entity, $collection->get($id));
    }

    private function definition(): AttributeEntityDefinition
    {
        $definitions = (new AttributeEntityCompiler())->compile(PaymentTransferEntity::class);

        static::assertCount(1, $definitions);

        $definition = new AttributeEntityDefinition($definitions[0]);
        // the fields resolve their associations through the registry, the compiler pass injects it
        $definition->compile(new DefinitionInstanceRegistry(new ContainerBuilder(), [], []));

        return $definition;
    }
}
