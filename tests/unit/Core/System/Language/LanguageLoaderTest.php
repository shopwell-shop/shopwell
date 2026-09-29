<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Language;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Result;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder;
use Shopwell\Core\Framework\DataAbstractionLayer\Doctrine\FetchModeHelper;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Language\LanguageLoader;

/**
 * @internal
 */
#[Package('fundamentals@discovery')]
#[CoversClass(LanguageLoader::class)]
class LanguageLoaderTest extends TestCase
{
    public function testLoadWithoutLanguages(): void
    {
        $connection = $this->getConnectionMockObject();

        $loader = new LanguageLoader($connection);

        static::assertSame([], $loader->loadLanguages());
    }

    public function testLoadLanguages(): void
    {
        $languages = [
            [
                'array_key' => '018dcf1d5c3d701f96a2894079f6e79f',
                'id' => '018dcf1d5c3d701f96a2894079f6e79f',
                'code' => 'zh-CN',
                'parentId' => 'parentId',
                'parentCode' => 'zh-CN',
            ],
            [
                'array_key' => '018de49f23ea7db5b3afb5181b5a12a1',
                'id' => '018de49f23ea7db5b3afb5181b5a12a1',
                'code' => 'en-GB',
                'parentId' => 'parentId',
                'parentCode' => 'zh-CN',
            ],
        ];
        $connection = $this->getConnectionMockObject($languages);

        $loader = new LanguageLoader($connection);

        static::assertSame(FetchModeHelper::groupUnique($languages), $loader->loadLanguages());
    }

    /**
     * @param array<int, array<string, string|null>> $returnData
     */
    private function getConnectionMockObject(array $returnData = []): Connection
    {
        $connection = static::createStub(Connection::class);

        $queryBuilder = static::createStub(QueryBuilder::class);
        $queryBuilder->method('select')->willReturn($queryBuilder);
        $queryBuilder->method('from')->willReturn($queryBuilder);
        $queryBuilder->method('leftJoin')->willReturn($queryBuilder);

        $result = static::createStub(Result::class);
        $result->method('fetchAllAssociative')->willReturn($returnData);

        $queryBuilder->method('executeQuery')->willReturn($result);

        $connection
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);

        return $connection;
    }
}
