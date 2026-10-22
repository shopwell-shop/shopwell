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
use Shopwell\Core\System\Dictionary\Aggregate\DictionaryEntry\DictionaryEntryCollection;
use Shopwell\Core\System\Dictionary\Command\DictionarySeedCommand;
use Shopwell\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\NullOutput;
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

        $this->seedSalutationDictionary();

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

    public function testReturnsActiveSalutationEntriesSortedByPosition(): void
    {
        $this->browser->request(Request::METHOD_POST, '/store-api/dictionary/salutation', []);

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());
        static::assertArrayHasKey('elements', $response);

        $technicalNames = array_column($response['elements'], 'technicalName');
        static::assertSame(['mr', 'mrs', 'not_specified'], $technicalNames);
    }

    public function testInactiveEntriesAreFiltered(): void
    {
        $entryId = $this->getEntryIdByTechnicalName('mr');
        static::assertNotNull($entryId);

        /** @var EntityRepository<DictionaryEntryCollection> $repository */
        $repository = static::getContainer()->get('dictionary_entry.repository');
        $repository->update([['id' => $entryId, 'active' => false]], $this->getContext());

        $this->browser->request(Request::METHOD_POST, '/store-api/dictionary/salutation', []);

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        $technicalNames = array_column($response['elements'], 'technicalName');
        static::assertNotContains('mr', $technicalNames);
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
        $this->browser->request(Request::METHOD_POST, '/store-api/dictionary/salutation', []);

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        $labels = array_column($response['elements'], 'label');
        static::assertSame(['先生', '女士', '未知'], $labels);
    }

    private function seedSalutationDictionary(): void
    {
        $command = static::getContainer()->get(DictionarySeedCommand::class);
        $command->run(new StringInput(''), new NullOutput());
    }

    private function getEntryIdByTechnicalName(string $technicalName): ?string
    {
        $connection = static::getContainer()->get(Connection::class);

        $id = $connection->fetchOne(
            'SELECT LOWER(HEX(e.id)) FROM dictionary_entry e
             INNER JOIN dictionary_type dt ON dt.id = e.dictionary_type_id
             WHERE dt.technical_name = "salutation" AND e.technical_name = :technicalName',
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
