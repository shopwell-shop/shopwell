<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Binding;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\Binding\ApprovalBusinessTypeSourceDirectory;
use Shopwell\Core\Framework\ApprovalFlow\Binding\YamlApprovalBusinessTypeLoader;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Exception\ParseException;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(YamlApprovalBusinessTypeLoader::class)]
class YamlApprovalBusinessTypeLoaderTest extends TestCase
{
    private Filesystem $filesystem;

    private string $directory;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->directory = sys_get_temp_dir() . '/approval-business-types-' . bin2hex(random_bytes(8));
        $this->filesystem->mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->directory);
    }

    public function testLoadsDeterministicDeclarationsAndRetainsSchemaArtifacts(): void
    {
        $this->filesystem->mirror(__DIR__ . '/_fixtures', $this->directory);
        $this->filesystem->copy($this->directory . '/expense.yaml', $this->directory . '/another.yml');
        $this->filesystem->mkdir($this->directory . '/nested');
        $this->filesystem->dumpFile($this->directory . '/nested/ignored.yaml', 'invalid');

        $types = $this->loader()->load();

        static::assertSame(['finance:another.yml', 'finance:expense.yaml'], array_column($types, 'source'));
        static::assertSame('finance:expense', $types[0]->type);
        static::assertSame('1.0.0', $types[0]->version);
        static::assertSame($this->filesystem->readFile($this->directory . '/expense.json'), $types[0]->formSchema);
        static::assertSame('urn:finance:expense:1', $types[0]->formSchemaId);
    }

    public function testMissingOptionalDirectoriesContributeNothing(): void
    {
        $loader = new YamlApprovalBusinessTypeLoader($this->filesystem, [new ApprovalBusinessTypeSourceDirectory('absent', $this->directory . '/absent')]);

        static::assertSame([], $loader->load());
    }

    #[DataProvider('invalidDeclarations')]
    public function testRejectsMalformedDeclarations(string $yaml, string $reason): void
    {
        $this->filesystem->dumpFile($this->directory . '/expense.yaml', $yaml);
        $this->expectExceptionObject(ApprovalFlowException::invalidBusinessType('finance:expense.yaml', $reason));

        $this->loader()->load();
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidDeclarations(): iterable
    {
        yield 'list is not a declaration' => ['[]', 'declaration must be a map'];
        yield 'unknown fields do not get ignored' => ['typo: value', 'Unknown declaration field'];
        yield 'required identity' => ['{}', 'type must be a string'];
        yield 'scalar field must be typed' => ['type: 7', 'type must be a string'];
        yield 'no path traversal' => ["type: finance:expense\nversion: 1.0.0\nlabelSnippet: finance.expense\nformSchema: ../private.json", 'formSchema must name a JSON file beside the declaration'];
        yield 'missing schema has a declaration error' => ["type: finance:expense\nversion: 1.0.0\nlabelSnippet: finance.expense\nformSchema: missing.json", 'formSchema file is missing'];
    }

    public function testYamlParseFailureKeepsSourceAndCause(): void
    {
        $this->filesystem->dumpFile($this->directory . '/expense.yaml', 'type: [');
        try {
            $this->loader()->load();
            static::fail('A broken declaration cannot be ignored.');
        } catch (ApprovalFlowException $exception) {
            static::assertSame(ApprovalFlowException::INVALID_BUSINESS_TYPE, $exception->getErrorCode());
            static::assertInstanceOf(ParseException::class, $exception->getPrevious());
            static::assertStringContainsString('finance:expense.yaml', $exception->getMessage());
        }
    }

    public function testSchemaSymlinkCannotReadOutsideTheDeclarationDirectory(): void
    {
        $declarations = $this->directory . '/declarations';
        $this->filesystem->mkdir($declarations);
        $this->filesystem->copy(__DIR__ . '/_fixtures/expense.yaml', $declarations . '/expense.yaml');
        $this->filesystem->copy(__DIR__ . '/_fixtures/expense.json', $this->directory . '/outside.json');
        $this->filesystem->symlink($this->directory . '/outside.json', $declarations . '/expense.json');
        $loader = new YamlApprovalBusinessTypeLoader($this->filesystem, [new ApprovalBusinessTypeSourceDirectory('finance', $declarations)]);
        $this->expectExceptionObject(ApprovalFlowException::invalidBusinessType('finance:expense.yaml', 'formSchema must resolve to a JSON file beside the declaration'));

        $loader->load();
    }

    public function testAResolvedSchemaBesideASymlinkedDeclarationDirectoryIsAllowed(): void
    {
        $this->filesystem->mirror(__DIR__ . '/_fixtures', $this->directory . '/declarations');
        $this->filesystem->symlink($this->directory . '/declarations', $this->directory . '/linked');
        $loader = new YamlApprovalBusinessTypeLoader($this->filesystem, [new ApprovalBusinessTypeSourceDirectory('finance', $this->directory . '/linked')]);

        static::assertSame('urn:finance:expense:1', $loader->load()[0]->formSchemaId);
    }

    public function testUnreadableSchemaKeepsTheDeclarationSourceAndFilesystemCause(): void
    {
        $this->filesystem->copy(__DIR__ . '/_fixtures/expense.yaml', $this->directory . '/expense.yaml');
        $this->filesystem->mkdir($this->directory . '/expense.json');
        try {
            $this->loader()->load();
            static::fail('A directory cannot serve as a form schema file.');
        } catch (ApprovalFlowException $exception) {
            static::assertSame(ApprovalFlowException::INVALID_BUSINESS_TYPE, $exception->getErrorCode());
            static::assertStringContainsString('finance:expense.yaml', $exception->getMessage());
            static::assertInstanceOf(IOExceptionInterface::class, $exception->getPrevious());
        }
    }

    public function testDeclarationReadFailureKeepsTheSourceAndOriginalCause(): void
    {
        $cause = new IOException('Declaration read failed.');
        $filesystem = static::createStub(Filesystem::class);
        $filesystem->method('exists')->willReturn(true);
        $filesystem->method('readFile')->willThrowException($cause);
        $loader = new YamlApprovalBusinessTypeLoader($filesystem, [new ApprovalBusinessTypeSourceDirectory('finance', __DIR__ . '/_fixtures')]);
        $this->expectExceptionObject(ApprovalFlowException::invalidBusinessType('finance:expense.yaml', 'declaration could not be read', $cause));

        $loader->load();
    }

    private function loader(): YamlApprovalBusinessTypeLoader
    {
        return new YamlApprovalBusinessTypeLoader($this->filesystem, [new ApprovalBusinessTypeSourceDirectory('finance', $this->directory)]);
    }
}
