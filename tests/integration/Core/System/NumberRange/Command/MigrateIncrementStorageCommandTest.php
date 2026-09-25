<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\System\NumberRange\Command;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\NumberRange\Command\MigrateIncrementStorageCommand;
use Shopwell\Core\System\NumberRange\ValueGenerator\Pattern\IncrementStorage\IncrementSqlStorage;
use Shopwell\Core\System\NumberRange\ValueGenerator\Pattern\IncrementStorage\IncrementStorageRegistry;
use Shopwell\Core\Test\Stub\System\NumberRange\ValueGenerator\IncrementArrayStorage;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * @internal
 */
#[Package('framework')]
class MigrateIncrementStorageCommandTest extends TestCase
{
    use IntegrationTestBehaviour;

    private IncrementSqlStorage $sqlStorage;

    private IncrementArrayStorage $arrayStorage;

    private CommandTester $tester;

    protected function setUp(): void
    {
        $this->sqlStorage = static::getContainer()->get(IncrementSqlStorage::class);
        $this->arrayStorage = new IncrementArrayStorage([]);

        $command = new MigrateIncrementStorageCommand(
            new IncrementStorageRegistry(new ServiceLocator([
                'SQL' => fn () => $this->sqlStorage,
                'Array' => fn () => $this->arrayStorage,
            ]), 'SQL')
        );

        $this->tester = new CommandTester($command);
    }

    public function testMigrateWithConfirmation(): void
    {
        $this->sqlStorage->set(Uuid::randomHex(), 10);
        static::assertNotEmpty($this->sqlStorage->list());
        $before = $this->arrayStorage->list();
        static::assertEmpty($before);

        $this->tester->setInputs(['yes']);
        $this->tester->execute(['from' => 'SQL', 'to' => 'Array']);

        $this->tester->assertCommandIsSuccessful();

        $after = $this->arrayStorage->list();
        static::assertNotEmpty($after);
        static::assertSame($this->sqlStorage->list(), $this->arrayStorage->list());
    }

    public function testMigrateWithUserAbort(): void
    {
        $this->sqlStorage->set(Uuid::randomHex(), 10);
        static::assertNotEmpty($this->sqlStorage->list());
        static::assertEmpty($this->arrayStorage->list());

        $this->tester->setInputs(['no']);
        $this->tester->execute(['from' => 'SQL', 'to' => 'Array']);

        static::assertSame(Command::FAILURE, $this->tester->getStatusCode());

        static::assertEmpty($this->arrayStorage->list());
    }
}
