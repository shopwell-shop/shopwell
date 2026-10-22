<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\System\Dictionary\Command;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\System\Dictionary\Aggregate\DictionaryEntry\DictionaryEntryCollection;
use Shopwell\Core\System\Dictionary\Command\DictionarySeedCommand;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * @internal
 */
#[Package('fundamentals@discovery')]
#[Group('dictionary')]
class DictionarySeedCommandTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testSeedIsIdempotentAndWritesBilingualLabels(): void
    {
        $command = static::getContainer()->get(DictionarySeedCommand::class);
        $output1 = new BufferedOutput();
        $exit1 = $command->run(new StringInput(''), $output1);
        static::assertSame(0, $exit1);

        $idsAfterFirstRun = $this->getEntryIds();

        $output2 = new BufferedOutput();
        $exit2 = $command->run(new StringInput(''), $output2);
        static::assertSame(0, $exit2);

        $idsAfterSecondRun = $this->getEntryIds();
        static::assertSame($idsAfterFirstRun, $idsAfterSecondRun, 'seed must not create duplicate rows');

        static::assertCount(3, $idsAfterSecondRun);

        $expected = [
            'mr' => ['en-GB' => 'Mr.', 'zh-CN' => '先生', 'position' => 1],
            'mrs' => ['en-GB' => 'Mrs.', 'zh-CN' => '女士', 'position' => 10],
            'not_specified' => ['en-GB' => 'Not specified', 'zh-CN' => '未知', 'position' => 100],
        ];

        foreach ($expected as $technicalName => $payload) {
            $labels = $this->getEntryLabels($technicalName);

            static::assertSame($payload['en-GB'], $labels['en-GB'], 'en-GB label of ' . $technicalName);
            static::assertSame($payload['zh-CN'], $labels['zh-CN'], 'zh-CN label of ' . $technicalName);
            static::assertSame($payload['position'], $this->getEntryPosition($technicalName));
        }
    }

    /**
     * @return array<string>
     */
    private function getEntryIds(): array
    {
        /** @var EntityRepository<DictionaryEntryCollection> $repository */
        $repository = static::getContainer()->get('dictionary_entry.repository');

        $ids = $repository->searchIds(
            $this->salutationTypeCriteria(),
            Context::createDefaultContext()
        )->getIds();

        sort($ids);

        return $ids;
    }

    /**
     * @return array<string, string>
     */
    private function getEntryLabels(string $technicalName): array
    {
        $connection = static::getContainer()->get(Connection::class);

        $rows = $connection->fetchAllAssociative(
            'SELECT lo.code AS code, t.label
             FROM dictionary_entry e
             INNER JOIN dictionary_type dt ON dt.id = e.dictionary_type_id
             INNER JOIN dictionary_entry_translation t ON t.dictionary_entry_id = e.id
             INNER JOIN language l ON l.id = t.language_id
             INNER JOIN locale lo ON lo.id = l.locale_id
             WHERE dt.technical_name = "salutation" AND e.technical_name = :technicalName',
            ['technicalName' => $technicalName]
        );

        $labels = [];
        foreach ($rows as $row) {
            $labels[$row['code']] = $row['label'];
        }

        return $labels;
    }

    private function getEntryPosition(string $technicalName): int
    {
        $connection = static::getContainer()->get(Connection::class);

        $position = $connection->fetchOne(
            'SELECT e.position FROM dictionary_entry e
             INNER JOIN dictionary_type dt ON dt.id = e.dictionary_type_id
             WHERE dt.technical_name = "salutation" AND e.technical_name = :technicalName',
            ['technicalName' => $technicalName]
        );

        return (int) $position;
    }

    private function salutationTypeCriteria(): Criteria
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('dictionaryType.technicalName', 'salutation'));

        return $criteria;
    }
}
