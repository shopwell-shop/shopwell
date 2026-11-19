<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Graph\_fixtures;

use Shopwell\Core\Framework\ApprovalFlow\Diagnostics\GraphDiagnostics;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Codec\GraphCodec;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Codec\GraphJsonDecoder;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Codec\GraphSchemaValidator;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Type\Loader\NodeTypeLoader;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Type\Loader\NodeTypeSourceDirectory;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Type\Loader\YamlNodeTypeLoader;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Type\Registry\NodeTypeRegistry;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('framework')]
final class GraphFixture
{
    public static function json(): string
    {
        return (new Filesystem())->readFile(__DIR__ . '/transfer.json');
    }

    /**
     * @param list<NodeTypeLoader> $extraLoaders
     */
    public static function diagnostics(array $extraLoaders = []): GraphDiagnostics
    {
        $filesystem = new Filesystem();
        $loader = new YamlNodeTypeLoader($filesystem, [new NodeTypeSourceDirectory('core', self::root() . '/Resources/node-types')]);
        $schema = $filesystem->readFile(self::root() . '/Resources/schema/graph/1.0.0/approval-graph.schema.json');

        return new GraphDiagnostics(
            new NodeTypeRegistry([$loader, ...$extraLoaders]),
            new GraphSchemaValidator(),
            $schema,
            new GraphJsonDecoder(),
        );
    }

    public static function codec(?GraphDiagnostics $diagnostics = null): GraphCodec
    {
        return new GraphCodec($diagnostics ?? self::diagnostics(), new GraphJsonDecoder());
    }

    private static function root(): string
    {
        return \dirname(__DIR__, 7) . '/src/Core/Framework/ApprovalFlow';
    }
}
