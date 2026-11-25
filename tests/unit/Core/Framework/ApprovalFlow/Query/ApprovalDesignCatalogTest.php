<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\ApprovalFlow\Binding\ApprovalBusinessTypeLoader;
use Shopwell\Core\Framework\ApprovalFlow\Binding\ApprovalBusinessTypeSpecification;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Type\Loader\NodeTypeLoader;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Type\NodeEffectType;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Type\Specification\NodeTypeSpecification;
use Shopwell\Core\Framework\ApprovalFlow\Query\ApprovalDesignCatalog;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ApprovalDesignCatalog::class)]
class ApprovalDesignCatalogTest extends TestCase
{
    public function testTaggedExtensionsAppearAtTheirExactVersionsWithUnchangedSchemas(): void
    {
        $container = new ContainerBuilder();
        $container->register('filesystem', Filesystem::class);
        (new PhpFileLoader($container, new FileLocator(\dirname(__DIR__, 6) . '/src/Core/Framework/DependencyInjection')))->load('approval-flow.php');
        foreach (['1.0.0', '2.0.0'] as $version) {
            $container->register('expense.nodes.' . $version, ExpenseDesignNodeLoader::class)
                ->setArgument(0, $version)->addTag('approval_flow.node_type_loader');
        }
        $container->register('expense.business', ExpenseDesignBusinessLoader::class)->addTag('approval_flow.business_type_loader');
        $container->getDefinition(ApprovalDesignCatalog::class)->setPublic(true);
        $container->compile();
        $catalog = $container->get(ApprovalDesignCatalog::class);
        static::assertInstanceOf(ApprovalDesignCatalog::class, $catalog);

        $response = $catalog->read();
        $types = array_column($response['nodeTypes'], null, 'key');
        static::assertArrayHasKey('core:start@1.0.0', $types);
        foreach (['1.0.0', '2.0.0'] as $version) {
            $type = $types['expense:review@' . $version];
            static::assertSame($version, $type['version']);
            static::assertSame(ExpenseDesignNodeLoader::SCHEMA, $type['configurationSchema']);
            static::assertSame(hash('sha256', ExpenseDesignNodeLoader::SCHEMA), $type['configurationSchemaHash']);
            static::assertSame(['advance'], $type['allowedEffects']);
            static::assertSame('{}', json_encode($type['ports'], \JSON_THROW_ON_ERROR));
            static::assertArrayNotHasKey('source', $type);
        }
        static::assertSame('expense:claim@1.0.0', $response['businessTypes'][0]['key']);
        static::assertSame(ExpenseDesignBusinessLoader::SCHEMA, $response['businessTypes'][0]['formSchema']);
        static::assertSame('{"$id":"urn:expense:review","type":"object","properties":{}}', $types['expense:review@1.0.0']['draftConfigurationSchema']);
        $graphSchema = json_decode($response['graphSchema'], true, flags: \JSON_THROW_ON_ERROR);
        static::assertSame('urn:shopwell:approval-flow:graph:1.0.0', $graphSchema['$id']);
        static::assertArrayNotHasKey('executors', $response);
    }
}

/**
 * @internal
 */
class ExpenseDesignNodeLoader implements NodeTypeLoader
{
    public const SCHEMA = <<<'JSON'
{
  "$id":"urn:expense:review","type":"object","properties":{}
}
JSON;

    public function __construct(private readonly string $version)
    {
    }

    public function load(): array
    {
        return [new NodeTypeSpecification('expense:review', $this->version, 'expense.review', '/private/plugin/expense.yaml', self::SCHEMA, [], [NodeEffectType::Advance], '{"$id":"urn:expense:review","type":"object","properties":{}}')];
    }
}

/**
 * @internal
 */
class ExpenseDesignBusinessLoader implements ApprovalBusinessTypeLoader
{
    public const SCHEMA = '{"$id":"urn:expense:claim","type":"object","properties":{}}';

    public function load(): array
    {
        return [new ApprovalBusinessTypeSpecification('expense:claim', '1.0.0', 'expense.claim', '/private/expense.yaml', self::SCHEMA)];
    }
}
