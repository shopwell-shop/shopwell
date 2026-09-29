<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\DataAbstractionLayer\Validation;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEventFactory;
use Shopwell\Core\Framework\DataAbstractionLayer\Read\EntityReaderInterface;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntityAggregatorInterface;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
use Shopwell\Core\Framework\DataAbstractionLayer\Validation\EntityNotExists;
use Shopwell\Core\Framework\DataAbstractionLayer\VersionManager;
use Shopwell\Core\Framework\Feature;
use Shopwell\Core\Framework\FrameworkException;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Locale\LocaleCollection;
use Shopwell\Core\System\Locale\LocaleDefinition;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Validator\ValidatorBuilder;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('framework')]
class EntityNotExistsValidatorTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testCriteriaObjectIsNotModified(): void
    {
        $criteria = new Criteria();
        $criteria->setLimit(50);

        $context = Context::createDefaultContext();
        $constraint = new EntityNotExists(
            entity: LocaleDefinition::ENTITY_NAME,
            context: $context,
            criteria: $criteria
        );

        $validator = $this->getValidator();

        $validator->validate(Uuid::randomHex(), $constraint);

        static::assertCount(0, $criteria->getFilters());
        static::assertSame(50, $criteria->getLimit());
    }

    public function testPrimaryPropertyIsString(): void
    {
        $context = Context::createDefaultContext();
        $constraint = new EntityNotExists(
            entity: LocaleDefinition::ENTITY_NAME,
            context: $context,
            primaryProperty: 'code'
        );

        $validator = $this->getValidator();

        $violations = $validator->validate(Uuid::randomHex(), $constraint);
        static::assertCount(0, $violations);
    }

    public function testPrimaryPropertyIsNotString(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);

        static::expectException(FrameworkException::class);

        $context = Context::createDefaultContext();
        /* @phpstan-ignore argument.type  (for test purpose) */
        $constraint = new EntityNotExists(['context' => $context, 'entity' => LocaleDefinition::ENTITY_NAME, 'primaryProperty' => 1]);

        $validator = $this->getValidator();

        $validator->validate(Uuid::randomHex(), $constraint);
    }

    public function testValidatorWorks(): void
    {
        $repository = $this->createRepository();

        $context = Context::createDefaultContext();
        $id1 = Uuid::randomHex();
        $id2 = Uuid::randomHex();

        $repository->create(
            [
                ['id' => $id1, 'name' => 'Test 1', 'territory' => 'test', 'code' => 'zh-CN-' . $id1],
                ['id' => $id2, 'name' => 'Test 2', 'territory' => 'test', 'code' => 'zh-CN-' . $id2],
            ],
            $context
        );

        $validator = $this->getValidator();

        $constraint = new EntityNotExists(
            entity: LocaleDefinition::ENTITY_NAME,
            context: $context,
        );

        $violations = $validator->validate($id1, $constraint);
        // Entity exists and therefore there is one violation.
        static::assertCount(1, $violations);

        $violations = $validator->validate($id2, $constraint);
        // Entity exists and therefore there is one violation.
        static::assertCount(1, $violations);

        $violations = $validator->validate(Uuid::randomHex(), $constraint);
        // Entity does not exist and therefore there are no violations.
        static::assertCount(0, $violations);
    }

    public function testValidatorWorksWithCompositeConstraint(): void
    {
        $repository = $this->createRepository();

        $context = Context::createDefaultContext();
        $id1 = Uuid::randomHex();
        $id2 = Uuid::randomHex();

        $repository->create(
            [
                ['id' => $id1, 'name' => 'Test 1', 'territory' => 'test', 'code' => 'zh-CN-' . $id1],
                ['id' => $id2, 'name' => 'Test 2', 'territory' => 'test', 'code' => 'zh-CN-' . $id2],
            ],
            $context
        );

        $validator = $this->getValidator();

        $constraint = new All(
            constraints: [
                new EntityNotExists(
                    entity: LocaleDefinition::ENTITY_NAME,
                    context: $context,
                ),
            ],
        );

        $violations = $validator->validate([Uuid::randomHex(), Uuid::randomHex()], $constraint);

        // No violations as both entities do not exist.
        static::assertCount(0, $violations);

        $violations = $validator->validate([Uuid::randomHex(), $id1, Uuid::randomHex(), $id2], $constraint);

        // Two violations as two entities exist.
        static::assertCount(2, $violations);
    }

    /**
     * @return EntityRepository<LocaleCollection>
     */
    protected function createRepository(): EntityRepository
    {
        $definition = static::getContainer()->get(LocaleDefinition::class);
        static::assertInstanceOf(LocaleDefinition::class, $definition);

        /** @var EntityRepository<LocaleCollection> */
        return new EntityRepository(
            $definition,
            static::getContainer()->get(EntityReaderInterface::class),
            static::getContainer()->get(VersionManager::class),
            static::getContainer()->get(EntitySearcherInterface::class),
            static::getContainer()->get(EntityAggregatorInterface::class),
            static::getContainer()->get(EventDispatcherInterface::class),
            static::getContainer()->get(EntityLoadedEventFactory::class)
        );
    }

    protected function getValidator(): ValidatorInterface
    {
        return $this->getValidatorBuilder()->getValidator();
    }

    protected function getValidatorBuilder(): ValidatorBuilder
    {
        return static::getContainer()->get('validator.builder');
    }
}
