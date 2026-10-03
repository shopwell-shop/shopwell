<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\Api;

use PHPUnit\Framework\TestCase;
use Shopwell\Administration\Snippet\AppAdministrationSnippetDefinition;
use Shopwell\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopwell\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopwell\Core\Framework\Feature;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Notification\NotificationDefinition;
use Shopwell\Core\Framework\Test\DataAbstractionLayer\Field\DataAbstractionLayerFieldTestBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\Framework\Util\Hasher;
use Shopwell\Storefront\Theme\ThemeDefinition;

/**
 * @internal
 */
#[Package('framework')]
class ApiAwareTest extends TestCase
{
    use DataAbstractionLayerFieldTestBehaviour;
    use KernelTestBehaviour;

    public function testApiAware(): void
    {
        $cacheId = Hasher::hashFile(__DIR__ . '/fixtures/api-aware-fields.json');

        $kernel = KernelLifecycleManager::createKernel(
            null,
            true,
            $cacheId
        );
        $kernel->boot();
        $registry = $kernel->getContainer()->get(DefinitionInstanceRegistry::class);

        $mapping = [];

        foreach ($registry->getDefinitions() as $definition) {
            $entity = $definition->getEntityName();

            foreach ($definition->getFields() as $field) {
                $flag = $field->getFlag(ApiAware::class);
                if ($flag === null) {
                    continue;
                }

                if ($flag->isSourceAllowed(SalesChannelApiSource::class)) {
                    $mapping[] = $entity . '.' . $field->getPropertyName();
                }
            }
        }

        //        file_put_contents(__DIR__ . '/fixtures/api-aware-fields.json', json_encode($mapping, JSON_PRETTY_PRINT));

        // To update the mapping you can simply comment the following line and run the test once. The mapping will then be updated.
        // The line to update the mapping must of course be commented out again afterwards.
        $expected = file_get_contents(__DIR__ . '/fixtures/api-aware-fields.json');
        if (!\is_string($expected)) {
            static::fail(__DIR__ . '/fixtures/api-aware-fields.json could not be read');
        }
        $expected = \json_decode($expected, true, flags: \JSON_THROW_ON_ERROR);

        if (Feature::isActive('v6.8.0.0')) {
            // Deprecated fields removed with v6.8.0.0; user_recovery.createdAt loses ApiAware
            // because defineFields() now overrides the ApiAware default field
            $expected = array_values(array_diff($expected, [
                'user_recovery.createdAt',
                'category.cmsPageIdSwitched',
                'product.states',
                'order_address.vatId',
                'order_line_item.states',
                // the profile label translation is removed with v6.8 (#18097)
                'import_export_profile.translated',
                'import_export_profile_translation.createdAt',
                'import_export_profile_translation.updatedAt',
                'import_export_profile_translation.importExportProfileId',
                'import_export_profile_translation.languageId',
            ]));
        }

        if (static::getContainer()->has(ThemeDefinition::class)) {
            $expected = array_merge(
                $expected,
                [
                    'theme.id',
                    'theme.technicalName',
                    'theme.name',
                    'theme.author',
                    'theme.description',
                    'theme.labels',
                    'theme.helpTexts',
                    'theme.customFields',
                    'theme.previewMediaId',
                    'theme.parentThemeId',
                    'theme.baseConfig',
                    'theme.configValues',
                    'theme.active',
                    'theme.media',
                    'theme.createdAt',
                    'theme.updatedAt',
                    'theme.translated',
                    'theme_translation.description',
                    'theme_translation.labels',
                    'theme_translation.helpTexts',
                    'theme_translation.customFields',
                    'theme_translation.createdAt',
                    'theme_translation.updatedAt',
                    'theme_translation.themeId',
                    'theme_translation.languageId',
                ]
            );
        }

        if (static::getContainer()->has(NotificationDefinition::class)) {
            $expected = array_merge(
                $expected,
                [
                    'notification.createdAt',
                    'notification.updatedAt',
                ]
            );
        }

        if (static::getContainer()->has(AppAdministrationSnippetDefinition::class)) {
            $expected = array_merge(
                $expected,
                [
                    'app_administration_snippet.value',
                    'app_administration_snippet.appId',
                    'app_administration_snippet.localeId',
                    'app_administration_snippet.createdAt',
                    'app_administration_snippet.updatedAt',
                ]
            );
        }

        $message = 'One or more fields have been changed in their visibility for the Store Api.
        This change must be carefully controlled to ensure that no sensitive data is given out via the Store API.';

        $diff = array_diff($mapping, $expected);
        static::assertSame([], $diff, $message);

        $diff = array_diff($expected, $mapping);
        static::assertSame([], $diff, $message);
    }
}
