<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\DataAbstractionLayer\Dbal;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerCollection;
use Shopwell\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Shopwell\Core\Content\Product\Aggregate\ProductManufacturerTranslation\ProductManufacturerTranslationDefinition;
use Shopwell\Core\Content\Product\ProductEntity;
use Shopwell\Core\Content\Test\Product\ProductBuilder;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Dbal\FieldResolver\FieldResolverContext;
use Shopwell\Core\Framework\DataAbstractionLayer\Dbal\FieldResolver\TranslationFieldResolver;
use Shopwell\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
#[Package('framework')]
class TranslatedVersionsTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var string[]
     */
    private array $languages = [
        'en-GB', 'zh-CN',
    ];

    /**
     * @var EntityRepository<ProductManufacturerCollection>
     */
    private EntityRepository $manufacturerRepository;

    protected function setUp(): void
    {
        $this->manufacturerRepository = static::getContainer()->get('product_manufacturer.repository');
    }

    public function testTranslationsAreAllSelectable(): void
    {
        $enContext = Context::createDefaultContext();
        $manufacturerId = Uuid::randomHex();

        $this->createManufacturer($this->manufacturerRepository, $manufacturerId, $enContext);

        $versionId = $this->manufacturerRepository->createVersion($manufacturerId, $enContext);
        $enVersionContext = $enContext->createWithVersionId($versionId);
        $zhContext = $this->createZhContext($enContext);
        $zhVersionContext = $zhContext->createWithVersionId($versionId);

        $this->manufacturerRepository->update([[
            'id' => $manufacturerId,
            'name' => 'version-en-GB',
        ]], $enVersionContext);

        $this->manufacturerRepository->update([[
            'id' => $manufacturerId,
            'name' => 'version-zh-CN',
        ]], $zhVersionContext);

        $enOriginal = $this->manufacturerRepository->search(new Criteria([$manufacturerId]), $enContext)->getEntities()->first();
        $enVersion = $this->manufacturerRepository->search(new Criteria([$manufacturerId]), $enVersionContext)->getEntities()->first();
        $zhOriginal = $this->manufacturerRepository->search(new Criteria([$manufacturerId]), $zhContext)->getEntities()->first();
        $zhVersion = $this->manufacturerRepository->search(new Criteria([$manufacturerId]), $zhVersionContext)->getEntities()->first();

        static::assertNotNull($enOriginal);
        static::assertNotNull($enVersion);
        static::assertNotNull($zhOriginal);
        static::assertNotNull($zhVersion);
        static::assertSame('original-en-GB', $enOriginal->getName());
        static::assertSame('version-en-GB', $enVersion->getName());
        static::assertSame('original-zh-CN', $zhOriginal->getName());
        static::assertSame('version-zh-CN', $zhVersion->getName());
    }

    public function testTranslationsFallbackToOriginal(): void
    {
        $enContext = Context::createDefaultContext();
        $manufacturerId = Uuid::randomHex();

        $this->createManufacturer($this->manufacturerRepository, $manufacturerId, $enContext);

        $versionId = $this->manufacturerRepository->createVersion($manufacturerId, $enContext);
        $enVersionContext = $enContext->createWithVersionId($versionId);
        $zhContext = $this->createZhContext($enContext);
        $zhVersionContext = $zhContext->createWithVersionId($versionId);

        $this->manufacturerRepository->update([[
            'id' => $manufacturerId,
            'name' => 'version-en-GB',
        ]], $enVersionContext);

        $enOriginal = $this->manufacturerRepository->search(new Criteria([$manufacturerId]), $enContext)->getEntities()->first();
        $enVersion = $this->manufacturerRepository->search(new Criteria([$manufacturerId]), $enVersionContext)->getEntities()->first();
        $zhOriginal = $this->manufacturerRepository->search(new Criteria([$manufacturerId]), $zhContext)->getEntities()->first();
        $zhVersion = $this->manufacturerRepository->search(new Criteria([$manufacturerId]), $zhVersionContext)->getEntities()->first();

        static::assertNotNull($enOriginal);
        static::assertNotNull($enVersion);
        static::assertNotNull($zhOriginal);
        static::assertNotNull($zhVersion);
        static::assertSame('original-en-GB', $enOriginal->getName());
        static::assertSame('version-en-GB', $enVersion->getName());
        static::assertSame('original-zh-CN', $zhOriginal->getName());
        static::assertSame('original-zh-CN', $zhVersion->getName());
    }

    public function testInheritenceWithProductsAllAreTranslated(): void
    {
        $enContext = Context::createDefaultContext();
        $zhContext = $this->createZhContext($enContext);
        $productRepository = static::getContainer()->get('product.repository');

        $ids = $this->createParentChildProduct();

        $enVersionContext = $enContext->createWithVersionId($productRepository->createVersion($ids->get('child'), $enContext));
        $zhVersionContext = $zhContext->createWithVersionId($productRepository->createVersion($ids->get('child'), $zhContext));
        $productRepository->update([['id' => $ids->get('child'), 'name' => 'child-version-en-GB']], $enVersionContext);
        $productRepository->update([['id' => $ids->get('child'), 'name' => 'child-version-zh-CN']], $zhVersionContext);

        $this->assertProductNames([
            ['child-original-en-GB', $enContext],
            ['child-original-zh-CN', $zhContext],
            ['child-version-en-GB', $enVersionContext],
            ['child-version-zh-CN', $zhVersionContext],
        ], $ids->get('child'));
        $this->assertProductNames([
            ['parent-original-en-GB', $enContext],
            ['parent-original-zh-CN', $zhContext],
            ['parent-original-en-GB', $enVersionContext],
            ['parent-original-zh-CN', $zhVersionContext],
        ], $ids->get('parent'));
    }

    public function testInheritanceWithProductsOnlyEnInVersionTranslated(): void
    {
        $enContext = Context::createDefaultContext();
        $zhContext = $this->createZhContext($enContext);
        $productRepository = static::getContainer()->get('product.repository');

        $ids = $this->createParentChildProduct();

        $enVersionContext = $enContext->createWithVersionId($productRepository->createVersion($ids->get('child'), $enContext));
        $zhVersionContext = $zhContext->createWithVersionId($productRepository->createVersion($ids->get('child'), $zhContext));
        $productRepository->update([['id' => $ids->get('child'), 'name' => 'child-version-en-GB']], $enVersionContext);

        $this->assertProductNames([
            ['child-original-en-GB', $enContext],
            ['child-original-zh-CN', $zhContext],
            ['child-version-en-GB', $enVersionContext],
            ['child-original-zh-CN', $zhVersionContext],
        ], $ids->get('child'));
        $this->assertProductNames([
            ['parent-original-en-GB', $enContext],
            ['parent-original-zh-CN', $zhContext],
            ['parent-original-en-GB', $enVersionContext],
            ['parent-original-zh-CN', $zhVersionContext],
        ], $ids->get('parent'));
    }

    public function testInheritanceWithOnlyParentTranslations(): void
    {
        $enContext = Context::createDefaultContext();
        $zhContext = $this->createZhContext($enContext);
        $productRepository = static::getContainer()->get('product.repository');

        $ids = $this->createParentChildProduct(false);

        $enVersionContext = $enContext->createWithVersionId($productRepository->createVersion($ids->get('child'), $enContext));
        $zhVersionContext = $zhContext->createWithVersionId($productRepository->createVersion($ids->get('child'), $zhContext));

        $this->assertProductNames([
            ['parent-original-en-GB', $enContext],
            ['parent-original-zh-CN', $zhContext],
            ['parent-original-en-GB', $enVersionContext],
            ['parent-original-zh-CN', $zhVersionContext],
        ], $ids->get('parent'));

        $this->assertProductNames([
            ['parent-original-en-GB', $enContext],
            ['parent-original-zh-CN', $zhContext],
            ['parent-original-en-GB', $enVersionContext],
            ['parent-original-zh-CN', $zhVersionContext],
        ], $ids->get('child'));
    }

    public function testFieldResolverThrowsOnNotTranslatedEntities(): void
    {
        $resolver = static::getContainer()->get(TranslationFieldResolver::class);
        $context = new FieldResolverContext(
            '',
            '',
            new TranslatedField(''),
            static::getContainer()->get(ProductManufacturerTranslationDefinition::class),
            static::getContainer()->get(ProductManufacturerTranslationDefinition::class),
            new QueryBuilder(static::getContainer()->get(Connection::class)),
            Context::createDefaultContext(),
            null
        );

        $this->expectException(\RuntimeException::class);
        $resolver->join($context);
    }

    public function testFieldResolverReturnsOnNotTranslatedFields(): void
    {
        $resolver = static::getContainer()->get(TranslationFieldResolver::class);
        $result = $resolver->join(new FieldResolverContext(
            '',
            'THIS_SHOULD_BE_RETURNED',
            new StringField('', ''),
            static::getContainer()->get(ProductManufacturerDefinition::class),
            static::getContainer()->get(ProductManufacturerDefinition::class),
            new QueryBuilder(static::getContainer()->get(Connection::class)),
            Context::createDefaultContext(),
            null
        ));

        static::assertSame('THIS_SHOULD_BE_RETURNED', $result);
    }

    /**
     * @param EntityRepository<ProductManufacturerCollection> $productManufacturerRepository
     */
    private function createManufacturer(EntityRepository $productManufacturerRepository, string $productManufacturerId, Context $context): void
    {
        $translations = $this->getTestTranslations();
        $productManufacturerRepository->create([[
            'id' => $productManufacturerId,
            'translations' => $translations,
        ]], $context);
    }

    private function createZhContext(Context $enContext): Context
    {
        $zhLanguageId = $this->getZhCnLanguageId();

        return new Context(
            $enContext->getSource(),
            $enContext->getRuleIds(),
            $enContext->getCurrencyId(),
            [$zhLanguageId, $enContext->getLanguageId()],
            $enContext->getVersionId(),
            $enContext->getCurrencyFactor(),
            $enContext->considerInheritance(),
            $enContext->getTaxState()
        );
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function getTestTranslations(string $prefix = ''): array
    {
        $translations = [];
        foreach ($this->languages as $locale) {
            $translations[$locale] = ['name' => $prefix . 'original-' . $locale];
        }

        return $translations;
    }

    /**
     * @param array<int, array<int, Context|string>> $assertions
     */
    private function assertProductNames(array $assertions, string $id): void
    {
        foreach ($assertions as [$name, $context]) {
            static::assertInstanceOf(Context::class, $context);
            static::assertIsString($name);
            $this->assertProductName($name, $id, $context);
        }
    }

    private function assertProductName(string $name, string $id, Context $context): void
    {
        $context->setConsiderInheritance(true);

        /** @var ProductEntity $product */
        $product = static::getContainer()
            ->get('product.repository')
            ->search(new Criteria([$id]), $context)->getEntities()->first();

        static::assertTrue($context->considerInheritance());
        static::assertSame($name, $product->getTranslated()['name'], \sprintf(
            'Expected %s with language chain %s but got %s, version context: %s',
            $name,
            (string) print_r($context->getLanguageIdChain(), true),
            (string) $product->getName(),
            $context->getVersionId() === Defaults::LIVE_VERSION ? 'NO' : 'YES'
        ));

        $context->setConsiderInheritance(false);
    }

    private function createParentChildProduct(bool $addChildTranslations = true): IdsCollection
    {
        $context = Context::createDefaultContext();
        $ids = new IdsCollection();

        $parentProduct = (new ProductBuilder($ids, 'parent'))
            ->price(100)
            ->build();

        $childProduct = (new ProductBuilder($ids, 'child'))
            ->parent('parent')
            ->price(100)
            ->build();

        unset($childProduct['name'], $parentProduct['name']);

        $parentProduct['translations'] = $this->getTestTranslations('parent-');

        if ($addChildTranslations) {
            $childProduct['translations'] = $this->getTestTranslations('child-');
        }

        static::getContainer()
            ->get('product.repository')
            ->create([$parentProduct, $childProduct], $context);

        return $ids;
    }
}
