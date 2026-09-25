<?php declare(strict_types=1);

namespace Shopwell\Core\Test\Stub\DataAbstractionLayer;

use Shopwell\Core\Framework\DataAbstractionLayer\Dbal\FieldAccessorBuilder\DefaultFieldAccessorBuilder;
use Shopwell\Core\Framework\DataAbstractionLayer\Dbal\FieldAccessorBuilder\FieldAccessorBuilderInterface;
use Shopwell\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer\BlobFieldSerializer;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer\BoolFieldSerializer;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer\CreatedAtFieldSerializer;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer\CustomFieldsSerializer;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer\FieldSerializerInterface;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer\FkFieldSerializer;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer\FloatFieldSerializer;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer\IdFieldSerializer;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer\IntFieldSerializer;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer\JsonFieldSerializer;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer\ListFieldSerializer;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer\LongTextFieldSerializer;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer\ManyToManyAssociationFieldSerializer;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer\ManyToOneAssociationFieldSerializer;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer\OneToManyAssociationFieldSerializer;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer\OneToOneAssociationFieldSerializer;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer\StringFieldSerializer;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer\UpdatedAtFieldSerializer;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\WriteCommandExtractor;
use Shopwell\Core\Framework\Util\HtmlSanitizer;
use Shopwell\Core\System\CustomField\CustomFieldService;
use Shopwell\Core\Test\Stub\Doctrine\FakeConnection;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @final
 */
class StaticDefinitionInstanceRegistry extends DefinitionInstanceRegistry
{
    /**
     * @var FieldSerializerInterface[]
     */
    private array $serializers;

    /**
     * @param array<int|string, class-string<EntityDefinition>|EntityDefinition> $registeredDefinitions
     */
    public function __construct(
        array $registeredDefinitions,
        private readonly ValidatorInterface $validator,
        private readonly EntityWriteGatewayInterface $entityWriteGateway,
        ContainerInterface $container = new ContainerBuilder()
    ) {
        parent::__construct($container, [], []);

        $this->setUpSerializers();

        foreach ($registeredDefinitions as $serviceId => $definition) {
            $this->register(
                $definition instanceof EntityDefinition ? $definition : new $definition(),
                \is_string($serviceId) ? $serviceId : null
            );
        }
    }

    public function getSerializer(string $serializerClass): FieldSerializerInterface
    {
        return $this->serializers[$serializerClass];
    }

    public function getAccessorBuilder(string $accessorBuilderClass): FieldAccessorBuilderInterface
    {
        return new DefaultFieldAccessorBuilder();
    }

    private function setUpSerializers(): void
    {
        $this->serializers = [
            IdFieldSerializer::class => new IdFieldSerializer($this->validator, $this),
            FkFieldSerializer::class => new FkFieldSerializer($this->validator, $this),
            StringFieldSerializer::class => new StringFieldSerializer($this->validator, $this, new HtmlSanitizer()),
            LongTextFieldSerializer::class => new LongTextFieldSerializer($this->validator, $this, new HtmlSanitizer()),
            IntFieldSerializer::class => new IntFieldSerializer($this->validator, $this),
            FloatFieldSerializer::class => new FloatFieldSerializer($this->validator, $this),
            BoolFieldSerializer::class => new BoolFieldSerializer($this->validator, $this),
            JsonFieldSerializer::class => new JsonFieldSerializer($this->validator, $this),
            ListFieldSerializer::class => new ListFieldSerializer($this->validator, $this),
            CreatedAtFieldSerializer::class => new CreatedAtFieldSerializer($this->validator, $this, new NativeClock()),
            UpdatedAtFieldSerializer::class => new UpdatedAtFieldSerializer($this->validator, $this, new NativeClock()),
            BlobFieldSerializer::class => new BlobFieldSerializer(),
            CustomFieldsSerializer::class => new CustomFieldsSerializer(
                $this,
                $this->validator,
                new CustomFieldService(new FakeConnection([['foo', 'int']]))
            ),
            ManyToManyAssociationFieldSerializer::class => new ManyToManyAssociationFieldSerializer(
                new WriteCommandExtractor($this->entityWriteGateway, $this),
            ),
            ManyToOneAssociationFieldSerializer::class => new ManyToOneAssociationFieldSerializer(
                new WriteCommandExtractor($this->entityWriteGateway, $this),
            ),
            OneToManyAssociationFieldSerializer::class => new OneToManyAssociationFieldSerializer(
                new WriteCommandExtractor($this->entityWriteGateway, $this),
            ),
            OneToOneAssociationFieldSerializer::class => new OneToOneAssociationFieldSerializer(
                new WriteCommandExtractor($this->entityWriteGateway, $this),
            ),
        ];
    }
}
