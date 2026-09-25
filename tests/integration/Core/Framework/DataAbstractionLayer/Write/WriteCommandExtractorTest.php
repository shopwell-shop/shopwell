<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\DataAbstractionLayer\Write;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopwell\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\EntityWriter;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\DataAbstractionLayer\Write\Entity\DefaultsChildDefinition;
use Shopwell\Core\Framework\Test\DataAbstractionLayer\Write\Entity\DefaultsChildTranslationDefinition;
use Shopwell\Core\Framework\Test\DataAbstractionLayer\Write\Entity\DefaultsDefinition;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('framework')]
class WriteCommandExtractorTest extends TestCase
{
    use IntegrationTestBehaviour;

    protected function setUp(): void
    {
        $this->stopTransactionAfter();
        $connection = static::getContainer()->get(Connection::class);

        $connection->executeStatement(DefaultsDefinition::SCHEMA);
        $connection->executeStatement(DefaultsChildDefinition::SCHEMA);
        $connection->executeStatement(DefaultsChildTranslationDefinition::SCHEMA);

        $this->startTransactionBefore();

        $defaultsDefinition = new DefaultsDefinition();
        $definitions = static::getContainer()->get(DefinitionInstanceRegistry::class);
        $definitions->register($defaultsDefinition);
        $definitions->register(new DefaultsChildDefinition());
        $definitions->register(new DefaultsChildTranslationDefinition());
    }

    protected function tearDown(): void
    {
        $this->stopTransactionAfter();
        $connection = static::getContainer()->get(Connection::class);

        $connection->executeStatement('DROP TABLE IF EXISTS ' . EntityDefinitionQueryHelper::escape('defaults_child_translation'));
        $connection->executeStatement('DROP TABLE IF EXISTS ' . EntityDefinitionQueryHelper::escape('defaults_child'));
        $connection->executeStatement('DROP TABLE IF EXISTS ' . EntityDefinitionQueryHelper::escape('defaults'));

        $this->startTransactionBefore();
    }

    public function testWriteWithNestedDefaults(): void
    {
        $context = WriteContext::createFromContext(Context::createDefaultContext());
        $writer = static::getContainer()->get(EntityWriter::class);

        $id = Uuid::randomHex();
        $defaultsDefinition = static::getContainer()->get(DefaultsDefinition::class);
        static::assertInstanceOf(DefaultsDefinition::class, $defaultsDefinition);
        $writeResults = $writer->insert($defaultsDefinition, [['id' => $id]], $context);

        static::assertCount(3, $writeResults);

        static::assertCount(1, $writeResults['defaults']);
        $defaultsWriteResult = $writeResults['defaults'][0];
        static::assertTrue($defaultsWriteResult->getPayload()['active']);

        static::assertCount(1, $writeResults['defaults_child']);
        $defaultsChildWriteResult = $writeResults['defaults_child'][0];
        static::assertSame($id, $defaultsChildWriteResult->getPayload()['defaultsId']);
        static::assertSame('Default foo', $defaultsChildWriteResult->getPayload()['foo']);
        $defaultsChildId = $defaultsChildWriteResult->getPayload()['id'];

        static::assertCount(1, $writeResults['defaults_child_translation']);
        $defaultsChildTranslationWriteResult = $writeResults['defaults_child_translation'][0];
        static::assertSame($defaultsChildId, $defaultsChildTranslationWriteResult->getPayload()['defaultsChildId']);
        static::assertSame(Defaults::LANGUAGE_SYSTEM, $defaultsChildTranslationWriteResult->getPayload()['languageId']);
        static::assertSame('Default name', $defaultsChildTranslationWriteResult->getPayload()['name']);
    }
}
