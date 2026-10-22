<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\System\Dictionary\SalesChannel;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Dictionary\Aggregate\DictionaryEntry\DictionaryEntryCollection;
use Shopwell\Core\System\Dictionary\Aggregate\DictionaryEntry\DictionaryEntryDefinition;
use Shopwell\Core\System\Dictionary\DictionaryTypeDefinition;
use Shopwell\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('fundamentals@discovery')]
#[Group('store-api')]
#[Group('dictionary')]
class DictionaryRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private KernelBrowser $browser;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();

        $this->seedGenderDictionary();

        $zhLanguageId = $this->getLanguageIdByLocale('zh-CN');
        static::assertNotNull($zhLanguageId);

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
            'languages' => [
                ['id' => Defaults::LANGUAGE_SYSTEM],
                ['id' => $zhLanguageId],
            ],
        ]);
    }

    public function testReturnsActiveGenderEntriesSortedByPosition(): void
    {
        $this->browser->request(Request::METHOD_POST, '/store-api/dictionary/gender', []);

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());
        static::assertArrayHasKey('elements', $response);

        $technicalNames = array_column($response['elements'], 'technicalName');
        static::assertSame(['male', 'female', 'not_specified'], $technicalNames);
    }

    public function testInactiveEntriesAreFiltered(): void
    {
        $entryId = $this->getEntryIdByTechnicalName('male');
        static::assertNotNull($entryId);

        /** @var EntityRepository<DictionaryEntryCollection> $repository */
        $repository = static::getContainer()->get('dictionary_entry.repository');
        $repository->update([['id' => $entryId, 'active' => false]], $this->getContext());

        $this->browser->request(Request::METHOD_POST, '/store-api/dictionary/gender', []);

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        $technicalNames = array_column($response['elements'], 'technicalName');
        static::assertNotContains('male', $technicalNames);
    }

    public function testUnknownTechnicalNameReturns404(): void
    {
        $this->browser->request(Request::METHOD_POST, '/store-api/dictionary/does_not_exist', []);

        static::assertSame(404, $this->browser->getResponse()->getStatusCode());
    }

    public function testReturnsLabelsForContextLanguage(): void
    {
        $zhLanguageId = $this->getLanguageIdByLocale('zh-CN');
        static::assertNotNull($zhLanguageId);

        $this->browser->setServerParameter('HTTP_SW_LANGUAGE_ID', $zhLanguageId);
        $this->browser->request(Request::METHOD_POST, '/store-api/dictionary/gender', []);

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        $labels = array_column($response['elements'], 'label');
        static::assertSame(['男', '女', '未知'], $labels);
    }

    /**
     * Seeds the gender dictionary the same way the BasicData migration does, but idempotently:
     * the integration database is reused across test runs, so existing rows are left untouched
     * and only missing rows are written.
     */
    private function seedGenderDictionary(): void
    {
        $connection = static::getContainer()->get(Connection::class);
        $now = (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        /** @var string|false $typeId */
        $typeId = $connection->fetchOne(
            'SELECT id FROM dictionary_type WHERE technical_name = :technicalName',
            ['technicalName' => DictionaryTypeDefinition::TYPE_GENDER]
        );

        if (!\is_string($typeId) || $typeId === '') {
            $typeId = Uuid::randomBytes();
            $connection->insert('dictionary_type', [
                'id' => $typeId,
                'technical_name' => DictionaryTypeDefinition::TYPE_GENDER,
                'created_at' => $now,
            ]);
        }

        $languageEn = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
        $languageZh = $this->getLanguageIdByLocale('zh-CN');

        $entries = [
            ['technical_name' => DictionaryEntryDefinition::MALE, 'position' => 1, 'en' => 'Male', 'zh' => '男'],
            ['technical_name' => DictionaryEntryDefinition::FEMALE, 'position' => 10, 'en' => 'Female', 'zh' => '女'],
            ['technical_name' => DictionaryEntryDefinition::NOT_SPECIFIED, 'position' => DictionaryEntryDefinition::DEFAULT_POSITION, 'en' => 'Not specified', 'zh' => '未知'],
        ];

        foreach ($entries as $entry) {
            /** @var string|false $entryId */
            $entryId = $connection->fetchOne(
                'SELECT id FROM dictionary_entry WHERE dictionary_type_id = :typeId AND technical_name = :technicalName',
                ['typeId' => $typeId, 'technicalName' => $entry['technical_name']]
            );

            if (\is_string($entryId) && $entryId !== '') {
                continue;
            }

            $entryId = Uuid::randomBytes();
            $connection->insert('dictionary_entry', [
                'id' => $entryId,
                'dictionary_type_id' => $typeId,
                'technical_name' => $entry['technical_name'],
                'position' => $entry['position'],
                'active' => 1,
                'created_at' => $now,
            ]);

            foreach ([[$languageEn, $entry['en']], [$languageZh, $entry['zh']]] as [$languageId, $label]) {
                if ($languageId === null) {
                    continue;
                }

                $connection->insert('dictionary_entry_translation', [
                    'dictionary_entry_id' => $entryId,
                    'language_id' => $languageId,
                    'label' => $label,
                    'created_at' => $now,
                ]);
            }
        }
    }

    private function getEntryIdByTechnicalName(string $technicalName): ?string
    {
        $connection = static::getContainer()->get(Connection::class);

        $id = $connection->fetchOne(
            'SELECT LOWER(HEX(e.id)) FROM dictionary_entry e
             INNER JOIN dictionary_type dt ON dt.id = e.dictionary_type_id
             WHERE dt.technical_name = "gender" AND e.technical_name = :technicalName',
            ['technicalName' => $technicalName]
        );

        return \is_string($id) && $id !== '' ? $id : null;
    }

    private function getLanguageIdByLocale(string $locale): ?string
    {
        $connection = static::getContainer()->get(Connection::class);

        $id = $connection->fetchOne(
            'SELECT LOWER(HEX(l.id)) FROM language l
             INNER JOIN locale lo ON lo.id = l.locale_id
             WHERE lo.code = :locale',
            ['locale' => $locale]
        );

        return \is_string($id) && $id !== '' ? $id : null;
    }

    private function getContext(): Context
    {
        return Context::createDefaultContext();
    }
}
