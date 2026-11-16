<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\System\AdminMenu;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\AdminMenu\AdminMenuItemEntity;
use Shopwell\Core\System\AdminMenu\AdminMenuRouteRegistry;
use Shopwell\Core\System\AdminMenu\AdminMenuXmlReader;
use Shopwell\Core\System\AdminMenu\MenuDefinitionCollector;
use Shopwell\Core\System\AdminMenu\Service\CoreMenuRowsBuilder;
use Shopwell\Core\System\AdminMenu\Service\CoreMenuSynchronizer;
use Shopwell\Core\System\AdminMenu\XmlAdminMenuProvider;

/**
 * @internal
 */
#[Package('fundamentals@framework')]
class XmlMenuTranslationTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    public function testXmlTranslationsAndCustomFieldsPersistAndPreserveAdministratorEdits(): void
    {
        $context = Context::createDefaultContext();
        $languages = static::getContainer()->get('language.repository');
        $languageId = $languages->searchIds((new Criteria())->addFilter(new EqualsFilter('locale.code', 'zh-CN')), $context)->firstId();
        if ($languageId === null) {
            $localeId = static::getContainer()->get('locale.repository')
                ->searchIds((new Criteria())->addFilter(new EqualsFilter('code', 'zh-CN')), $context)->firstId();
            static::assertNotNull($localeId);
            $languageId = Uuid::randomHex();
            $languages->create([[
                'id' => $languageId,
                'name' => 'Chinese menu test',
                'localeId' => $localeId,
                'translationCodeId' => $localeId,
                'parentId' => Defaults::LANGUAGE_SYSTEM,
            ]], $context);
        }
        $chineseContext = new Context($context->getSource(), languageIdChain: [$languageId, Defaults::LANGUAGE_SYSTEM]);
        $repository = static::getContainer()->get('admin_menu_item.repository');
        $provider = new XmlAdminMenuProvider((new AdminMenuXmlReader())->read(__DIR__ . '/../../../../unit/Core/System/AdminMenu/_fixtures/valid-menu.xml'));
        $registry = new AdminMenuRouteRegistry(new MenuDefinitionCollector([$provider]));
        (new CoreMenuSynchronizer($repository, $registry, new CoreMenuRowsBuilder()))->synchronize();
        $criteria = (new Criteria())->addFilter(new EqualsFilter('internalKey', 'example-page'));

        $english = $repository->search($criteria, $context)->getEntities()->first();
        $chinese = $repository->search($criteria, $chineseContext)->getEntities()->first();

        static::assertInstanceOf(AdminMenuItemEntity::class, $english);
        static::assertInstanceOf(AdminMenuItemEntity::class, $chinese);
        static::assertSame('Example', $english->getTitle());
        static::assertSame('示例', $chinese->getTitle());
        static::assertSame('岗位说明', $chinese->getCustomFields()['example_hint'] ?? null);
        static::assertEquals(['compact' => true, 'order' => 2, 'tags' => ['a', 'b']], $chinese->getCustomFields()['example_options']);
        static::assertSame(0, (new CoreMenuSynchronizer($repository, $registry, new CoreMenuRowsBuilder()))->synchronize());

        $repository->update([[
            'id' => $chinese->getId(),
            'translations' => [$languageId => ['title' => '我的示例', 'customFields' => ['example_hint' => '自定义说明']]],
        ]], $context);
        (new CoreMenuSynchronizer($repository, $registry, new CoreMenuRowsBuilder()))->synchronize();
        $customized = $repository->search($criteria, $chineseContext)->getEntities()->first();

        static::assertInstanceOf(AdminMenuItemEntity::class, $customized);
        static::assertTrue($customized->isConfigOverridden());
        static::assertSame('我的示例', $customized->getTitle());
        static::assertSame('自定义说明', $customized->getCustomFields()['example_hint'] ?? null);
    }
}
