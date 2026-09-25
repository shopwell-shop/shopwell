<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\Adapter\Twig\NamespaceHierarchy;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Adapter\Twig\NamespaceHierarchy\NamespaceHierarchyBuilder;
use Shopwell\Core\Framework\App\AppCollection;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 */
#[Package('framework')]
class NamespaceHierarchyBuilderTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testItAddsAppTemplateNamespaces(): void
    {
        /** @var EntityRepository<AppCollection> $appRepository */
        $appRepository = static::getContainer()->get('app.repository');

        $appRepository->create([
            [
                'name' => 'SwagApp',
                'active' => true,
                'path' => __DIR__ . '/Manifest/_fixtures/test',
                'version' => '0.0.1',
                'label' => 'test',
                'accessToken' => 'test',
                'integration' => [
                    'label' => 'test',
                    'accessKey' => 'test',
                    'secretAccessKey' => 'test',
                ],
                'aclRole' => [
                    'name' => 'SwagApp',
                ],
            ],
            [
                'name' => 'SwagThemeTest',
                'active' => true,
                'path' => __DIR__ . '/Manifest/_fixtures/test',
                'version' => '0.0.1',
                'label' => 'test',
                'accessToken' => 'test',
                'templateLoadPriority' => 2,
                'integration' => [
                    'label' => 'test',
                    'accessKey' => 'test',
                    'secretAccessKey' => 'test',
                ],
                'aclRole' => [
                    'name' => 'SwagThemeTest',
                ],
                'templates' => [
                    [
                        'template' => 'test',
                        'path' => 'storefront/base.html.twig',
                        'active' => true,
                    ],
                ],
            ],
        ], Context::createDefaultContext());

        $hierarchyBuilder = static::getContainer()->get(NamespaceHierarchyBuilder::class);

        $coreHierarchy = [
            'Profiling',
            'Elasticsearch',
            'Administration',
            'Framework',
            'Storefront',
            'Content',
        ];
        // Remove not installed core bundles from hierarchy
        $coreHierarchy = array_intersect($coreHierarchy, array_keys(static::getContainer()->getParameter('kernel.bundles')));

        static::assertSame([
            ...$coreHierarchy,
            'SwagThemeTest',
        ], array_keys($hierarchyBuilder->buildHierarchy()));
    }
}
