<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\System\CustomField;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\CustomField\CustomFieldCollection;
use Shopwell\Core\System\CustomField\CustomFieldEntity;

/**
 * @internal
 */
#[Package('framework')]
class CustomFieldSortingTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    /**
     * @var EntityRepository<CustomFieldCollection>
     */
    private EntityRepository $repository;

    protected function setUp(): void
    {
        $this->repository = static::getContainer()->get('custom_field.repository');
    }

    public function testSortsCustomFieldPositionsNumerically(): void
    {
        $context = Context::createDefaultContext();
        $ids = [Uuid::randomHex(), Uuid::randomHex(), Uuid::randomHex()];

        $this->repository->create([
            ['id' => $ids[0], 'name' => 'sorting_test_ten', 'type' => 'text', 'config' => ['customFieldPosition' => 10, 'extensionConfiguration' => ['enabled' => true]]],
            ['id' => $ids[1], 'name' => 'sorting_test_two', 'type' => 'text', 'config' => ['customFieldPosition' => 2]],
            ['id' => $ids[2], 'name' => 'sorting_test_one', 'type' => 'text', 'config' => ['customFieldPosition' => 1]],
        ], $context);

        $criteria = new Criteria($ids);
        $criteria->addSorting(new FieldSorting('config.customFieldPosition', FieldSorting::ASCENDING));

        $fields = $this->repository->search($criteria, $context)->getEntities();

        static::assertSame([$ids[2], $ids[1], $ids[0]], $fields->getKeys());
        static::assertInstanceOf(CustomFieldEntity::class, $fields->get($ids[0]));
        $config = $fields->get($ids[0])->getConfig();
        static::assertIsArray($config);
        static::assertArrayHasKey('extensionConfiguration', $config);
        static::assertSame(['enabled' => true], $config['extensionConfiguration']);
    }

    public function testPreservesNumberBoundTypes(): void
    {
        $id = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $this->repository->create([
            [
                'id' => $id,
                'name' => 'number_bounds_test',
                'type' => 'float',
                'config' => [
                    'min' => 1,
                    'max' => 2.5,
                    'step' => 1,
                ],
            ],
        ], $context);

        $customField = $this->repository->search(new Criteria([$id]), $context)->getEntities()->first();
        static::assertInstanceOf(CustomFieldEntity::class, $customField);
        $config = $customField->getConfig();
        static::assertIsArray($config);
        static::assertSame(1, $config['min']);
        static::assertSame(2.5, $config['max']);
        static::assertSame(1, $config['step']);
    }
}
