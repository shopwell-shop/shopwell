<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\DataAbstractionLayer\FieldSerializer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer\TranslatedFieldSerializer;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommandQueue;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\Container;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(TranslatedFieldSerializer::class)]
class TranslatedFieldSerializerTest extends TestCase
{
    private TranslatedFieldSerializer $serializer;

    private WriteContext $writeContext;

    private TranslatedSerializerDefinition $definition;

    protected function setUp(): void
    {
        $this->serializer = new TranslatedFieldSerializer();
        $this->writeContext = WriteContext::createFromContext(Context::createDefaultContext());
        $this->definition = new TranslatedSerializerDefinition();

        $registry = new DefinitionInstanceRegistry(new Container(), [], []);
        $registry->register($this->definition, TranslatedSerializerDefinition::class);
        $registry->register(new TranslatedSerializerTranslationDefinition(), TranslatedSerializerTranslationDefinition::class);
    }

    public function testNormalizeNullData(): void
    {
        $data = $this->normalize(['name' => null]);

        static::assertSame([
            'name' => null,
            'translations' => [
                $this->writeContext->getContext()->getLanguageId() => [
                    'name' => null,
                ],
            ],
        ], $data);
    }

    public function testNormalizeStringData(): void
    {
        $data = $this->normalize(['name' => 'abc']);

        static::assertSame([
            'name' => 'abc',
            'translations' => [
                $this->writeContext->getContext()->getLanguageId() => [
                    'name' => 'abc',
                ],
            ],
        ], $data);
    }

    public function testNormalizeArrayData(): void
    {
        $languageId = $this->writeContext->getContext()->getLanguageId();

        $data = $this->normalize([
            'name' => [
                $languageId => 'abc',
            ],
        ]);

        static::assertSame([
            'name' => [
                $languageId => 'abc',
            ],
            'translations' => [
                $languageId => [
                    'name' => 'abc',
                ],
            ],
        ], $data);
    }

    /**
     * @param array<string, string|array<string, string>|null> $data
     *
     * @return array<string, string|array<string, string>|null>
     */
    private function normalize(array $data): array
    {
        return $this->serializer->normalize(
            new TranslatedField('name'),
            $data,
            new WriteParameterBag($this->definition, $this->writeContext, '', new WriteCommandQueue())
        );
    }
}

/**
 * @internal
 */
class TranslatedSerializerDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'translated_serializer';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            new TranslatedField('name'),
            (new TranslationsAssociationField(TranslatedSerializerTranslationDefinition::class, 'translated_serializer_id'))->addFlags(new Required()),
        ]);
    }
}

/**
 * @internal
 */
class TranslatedSerializerTranslationDefinition extends EntityTranslationDefinition
{
    final public const ENTITY_NAME = 'translated_serializer_translation';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    protected function getParentDefinitionClass(): string
    {
        return TranslatedSerializerDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new StringField('name', 'name'))->addFlags(new Required()),
        ]);
    }
}
