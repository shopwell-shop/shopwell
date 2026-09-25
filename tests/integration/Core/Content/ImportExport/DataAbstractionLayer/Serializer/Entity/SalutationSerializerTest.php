<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity\SalutationSerializer;
use Shopwell\Core\Content\ImportExport\DataAbstractionLayer\Serializer\SerializerRegistry;
use Shopwell\Core\Content\ImportExport\Struct\Config;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Salutation\SalutationCollection;
use Shopwell\Core\System\Salutation\SalutationDefinition;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
class SalutationSerializerTest extends TestCase
{
    use KernelTestBehaviour;

    /**
     * @var EntityRepository<SalutationCollection>
     */
    private EntityRepository $salutationRepository;

    private SalutationSerializer $serializer;

    protected function setUp(): void
    {
        $this->salutationRepository = static::getContainer()->get('salutation.repository');
        $serializerRegistry = static::getContainer()->get(SerializerRegistry::class);

        $this->serializer = new SalutationSerializer($this->salutationRepository);
        $this->serializer->setRegistry($serializerRegistry);
    }

    public function testSimple(): void
    {
        $config = new Config([], [], []);

        $salutation = [
            'id' => Uuid::randomHex(),
            'salutationKey' => 'mrs',
            'translations' => [
                Defaults::LANGUAGE_SYSTEM => [
                    'displayName' => 'Mrs.',
                    'letterName' => 'Dear Mrs.',
                ],
            ],
        ];

        $serialized = iterator_to_array($this->serializer->serialize($config, $this->salutationRepository->getDefinition(), $salutation));

        $deserialized = iterator_to_array($this->serializer->deserialize($config, $this->salutationRepository->getDefinition(), $serialized));

        $expectedTranslations = $salutation['translations'][Defaults::LANGUAGE_SYSTEM];
        $actualTranslations = $deserialized['translations'][Defaults::LANGUAGE_SYSTEM];
        unset($salutation['translations'], $deserialized['translations']);

        static::assertSame($salutation, $deserialized);
        static::assertSame($expectedTranslations, $actualTranslations);
    }

    public function testDeserializeOnlySalutationKey(): void
    {
        $config = new Config([], [], []);

        $salutation = [
            'salutationKey' => 'mrs',
        ];

        $deserialized = iterator_to_array($this->serializer->deserialize($config, $this->salutationRepository->getDefinition(), $salutation));

        static::assertSame($salutation['salutationKey'], $deserialized['salutationKey']);
        static::assertArrayHasKey('id', $deserialized);

        $criteria = (new Criteria())->addFilter(new EqualsFilter('salutationKey', 'mrs'));
        $salutationId = $this->salutationRepository->searchIds($criteria, Context::createDefaultContext())->firstId();

        static::assertSame($salutationId, $deserialized['id']);
    }

    public function testUsesNotSpecifiedAsFallback(): void
    {
        $config = new Config([], [], []);

        $salutation = [
            'salutationKey' => 'unknown',
        ];

        $deserialized = iterator_to_array($this->serializer->deserialize($config, $this->salutationRepository->getDefinition(), $salutation));

        static::assertArrayNotHasKey('salutationKey', $deserialized);
        static::assertArrayHasKey('id', $deserialized);

        $criteria = (new Criteria())->addFilter(new EqualsFilter('salutationKey', 'not_specified'));
        $salutationId = $this->salutationRepository->searchIds($criteria, Context::createDefaultContext())->firstId();

        static::assertSame($salutationId, $deserialized['id']);
    }

    public function testSupportsOnlySalutation(): void
    {
        $serializer = new SalutationSerializer(static::getContainer()->get('salutation.repository'));

        $definitionRegistry = static::getContainer()->get(DefinitionInstanceRegistry::class);
        foreach ($definitionRegistry->getDefinitions() as $definition) {
            $entity = $definition->getEntityName();

            if ($entity === SalutationDefinition::ENTITY_NAME) {
                static::assertTrue($serializer->supports($entity));
            } else {
                static::assertFalse(
                    $serializer->supports($entity),
                    SalutationDefinition::class . ' should not support ' . $entity
                );
            }
        }
    }
}
