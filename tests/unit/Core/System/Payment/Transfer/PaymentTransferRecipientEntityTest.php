<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Transfer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\DataAbstractionLayer\AttributeEntityCompiler;
use Shopwell\Core\Framework\DataAbstractionLayer\AttributeEntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferRecipientCollection;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferRecipientEntity;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferStates;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentTransferRecipientEntity::class)]
class PaymentTransferRecipientEntityTest extends TestCase
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
        yield 'entity name' => ['payment_transfer_recipient', PaymentTransferRecipientEntity::ENTITY_NAME];
        yield 'api alias' => ['payment_transfer_recipient_collection', (new PaymentTransferRecipientCollection())->getApiAlias()];
    }

    /**
     * The definition is what the dal reads - a property that does not make it into it does not exist
     * for the repository, so it is built here the way the compiler pass builds it.
     */
    public function testTheDefinitionDescribesTheEntity(): void
    {
        $definition = $this->definition();

        static::assertSame(PaymentTransferRecipientEntity::ENTITY_NAME, $definition->getEntityName());
        static::assertSame(PaymentTransferRecipientCollection::class, $definition->getCollectionClass());
        static::assertSame(PaymentTransferRecipientEntity::class, $definition->getEntityClass());
        static::assertTrue($definition->hasCreatedAndUpdatedAtFields());
    }

    /**
     * These are the columns a writer has to spell out: every one of them is not nullable and none of
     * them carries a ddl default, so the dal would reject an insert without them. `id` and
     * `createdAt` are the two the dal fills itself.
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
            'createdAt',
            'id',
            'identityHash',
            'identityType',
            'identityValue',
            'onboardingStatus',
            'paymentAppId',
            'payoutEnabled',
            'provider',
        ], $required);
    }

    /**
     * Neither the onboarding status nor the submit attempts of a transfer may be defaulted: the dal
     * only knows `getDefaults()` and would never write a property initial value, so a default in the
     * definition would silently disagree with the one in the ddl.
     */
    public function testTheDefinitionDeclaresNoDefaults(): void
    {
        static::assertSame([], $this->definition()->getDefaults());
    }

    public function testPropertiesRoundTrip(): void
    {
        $entity = new PaymentTransferRecipientEntity();
        $entity->id = Uuid::randomHex();
        $entity->paymentAppId = Uuid::randomHex();
        $entity->provider = 'alipay';
        $entity->identityType = 'alipay_logon_id';
        $entity->identityValue = 'encrypted-payload';
        $entity->identityHash = hash('sha256', 'app|alipay|alipay_logon_id|someone@example.com');
        $entity->holderName = '张三';
        $entity->providerAccountId = '2088000000000000';
        $entity->bankInfo = ['account_type' => '2', 'inst_name' => '招商银行'];
        $entity->onboardingStatus = PaymentTransferStates::ONBOARDING_REGISTERED;
        $entity->payoutEnabled = true;
        $entity->verifiedAt = new \DateTimeImmutable('2026-10-18 09:00:00');
        $entity->metadata = ['source' => 'migration'];

        static::assertSame('alipay', $entity->provider);
        static::assertSame('alipay_logon_id', $entity->identityType);
        static::assertSame('encrypted-payload', $entity->identityValue);
        static::assertSame(hash('sha256', 'app|alipay|alipay_logon_id|someone@example.com'), $entity->identityHash);
        static::assertSame('张三', $entity->holderName);
        static::assertSame(['account_type' => '2', 'inst_name' => '招商银行'], $entity->bankInfo);
        static::assertSame(PaymentTransferStates::ONBOARDING_REGISTERED, $entity->onboardingStatus);
        static::assertTrue($entity->payoutEnabled);
        static::assertSame('2026-10-18 09:00:00', $entity->verifiedAt->format('Y-m-d H:i:s'));
        static::assertSame(['source' => 'migration'], $entity->metadata);
    }

    public function testPayoutIsDisabledUntilTheChannelEnablesIt(): void
    {
        $entity = new PaymentTransferRecipientEntity();

        static::assertFalse($entity->payoutEnabled);
    }

    public function testCollectionHoldsEntitiesById(): void
    {
        $entity = new PaymentTransferRecipientEntity();
        $id = Uuid::randomHex();
        $entity->id = $id;
        $entity->provider = 'paypal';

        $collection = new PaymentTransferRecipientCollection();
        $collection->set($id, $entity);

        static::assertSame($entity, $collection->get($id));
    }

    private function definition(): AttributeEntityDefinition
    {
        $definitions = (new AttributeEntityCompiler())->compile(PaymentTransferRecipientEntity::class);

        static::assertCount(1, $definitions);

        $definition = new AttributeEntityDefinition($definitions[0]);
        // the fields resolve their associations through the registry, the compiler pass injects it
        $definition->compile(new DefinitionInstanceRegistry(new ContainerBuilder(), [], []));

        return $definition;
    }
}
