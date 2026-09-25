<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\DataAbstractionLayer\FieldSerializer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopwell\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\UpdatedByField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer\UpdatedByFieldSerializer;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommandQueue;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Test\Annotation\DisabledFeatures;
use Shopwell\Core\Test\TestDefaults;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(UpdatedByFieldSerializer::class)]
class UpdatedByFieldSerializerTest extends TestCase
{
    private DefinitionInstanceRegistry&Stub $definitionInstanceRegistry;

    private ValidatorInterface&Stub $validator;

    private UpdatedByFieldSerializer $fieldSerializer;

    protected function setUp(): void
    {
        $this->definitionInstanceRegistry = static::createStub(DefinitionInstanceRegistry::class);
        $this->validator = static::createStub(ValidatorInterface::class);

        $this->fieldSerializer = new UpdatedByFieldSerializer(
            $this->validator,
            $this->definitionInstanceRegistry,
        );
    }

    public function testEncode(): void
    {
        $data = new KeyValuePair('key', null, false);
        $existence = static::createStub(EntityExistence::class);
        $existence->method('exists')->willReturn(true);
        $userId = Uuid::randomHex();

        $parameters = new WriteParameterBag(
            static::createStub(EntityDefinition::class),
            $this->createWriteContext($userId),
            '/',
            new WriteCommandQueue(),
        );

        $result = iterator_to_array($this->fieldSerializer->encode(
            new UpdatedByField([Context::USER_SCOPE]),
            $existence,
            $data,
            $parameters
        ));

        static::assertSame($userId, Uuid::fromBytesToHex($result['updated_by_id'] ?? ''));
    }

    public function testEncodeWithInvalidField(): void
    {
        $data = new KeyValuePair('key', null, false);
        $existence = static::createStub(EntityExistence::class);
        $parameters = new WriteParameterBag(
            static::createStub(EntityDefinition::class),
            $this->createWriteContext(null),
            '/',
            new WriteCommandQueue(),
        );

        $wrongField = new JsonField('key', 'key');

        $this->expectExceptionObject(DataAbstractionLayerException::invalidSerializerField(UpdatedByField::class, $wrongField));

        $this->fieldSerializer->encode(
            $wrongField,
            $existence,
            $data,
            $parameters
        )->current();
    }

    public function testEncodeWithoutExistingEntity(): void
    {
        $data = new KeyValuePair('key', null, false);
        $existence = static::createStub(EntityExistence::class);
        $existence->method('exists')->willReturn(false);
        $parameters = new WriteParameterBag(
            static::createStub(EntityDefinition::class),
            $this->createWriteContext(Uuid::randomHex()),
            '/',
            new WriteCommandQueue(),
        );

        $result = iterator_to_array($this->fieldSerializer->encode(
            new UpdatedByField([Context::USER_SCOPE]),
            $existence,
            $data,
            $parameters
        ));

        static::assertEmpty($result);
    }

    public function testEncodeWithInvalidScope(): void
    {
        $data = new KeyValuePair('key', null, false);
        $existence = static::createStub(EntityExistence::class);
        $existence->method('exists')->willReturn(true);

        $result = 'foo';
        Context::createDefaultContext()->scope('invalid-scope', function (Context $context) use ($data, $existence, &$result): void {
            $result = $this->fieldSerializer->encode(
                new UpdatedByField([Context::USER_SCOPE]),
                $existence,
                $data,
                new WriteParameterBag(
                    $this->createStub(EntityDefinition::class),
                    WriteContext::createFromContext($context),
                    '/',
                    new WriteCommandQueue(),
                )
            )->current();
        });

        static::assertNull($result);
    }

    public function testEncodeWithSalesChannelApiSource(): void
    {
        $data = new KeyValuePair('key', null, false);
        $existence = static::createStub(EntityExistence::class);
        $existence->method('exists')->willReturn(true);
        $parameters = new WriteParameterBag(
            static::createStub(EntityDefinition::class),
            $this->createWriteContext(null, Defaults::LIVE_VERSION, false),
            '/',
            new WriteCommandQueue(),
        );

        $result = iterator_to_array($this->fieldSerializer->encode(
            new UpdatedByField([Context::USER_SCOPE]),
            $existence,
            $data,
            $parameters
        ));

        static::assertEmpty($result);
    }

    /**
     * @deprecated tag:v6.8.0 - remove this test, as the behavior will be removed
     */
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testEncodeWithNoUserIdDeprecated(): void
    {
        $data = new KeyValuePair('key', null, false);
        $existence = static::createStub(EntityExistence::class);
        $existence->method('exists')->willReturn(true);
        $parameters = new WriteParameterBag(
            static::createStub(EntityDefinition::class),
            $this->createWriteContext(null),
            '/',
            new WriteCommandQueue(),
        );

        $result = iterator_to_array($this->fieldSerializer->encode(
            new UpdatedByField([Context::USER_SCOPE]),
            $existence,
            $data,
            $parameters
        ));

        static::assertEmpty($result);
    }

    public function testEncodeWithNoUserId(): void
    {
        $data = new KeyValuePair('key', null, false);
        $existence = static::createStub(EntityExistence::class);
        $existence->method('exists')->willReturn(true);
        $parameters = new WriteParameterBag(
            static::createStub(EntityDefinition::class),
            $this->createWriteContext(null),
            '/',
            new WriteCommandQueue(),
        );

        $result = iterator_to_array($this->fieldSerializer->encode(
            new UpdatedByField([Context::USER_SCOPE]),
            $existence,
            $data,
            $parameters
        ));

        static::assertSame(['updated_by_id' => null], $result);
    }

    private function createWriteContext(?string $userId, string $versionId = Defaults::LIVE_VERSION, bool $useAdminApiSource = true): WriteContext
    {
        if ($useAdminApiSource) {
            $source = new AdminApiSource($userId);
        } else {
            $source = new SalesChannelApiSource(TestDefaults::SALES_CHANNEL);
        }

        $context = Context::createDefaultContext($source)->createWithVersionId($versionId);

        return WriteContext::createFromContext($context);
    }
}
