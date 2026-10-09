<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\Migration;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationCollectionLoader;
use Shopwell\Core\Framework\Migration\MigrationStep;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\Migration\Test\NullConnection;

/**
 * @internal
 */
#[Package('framework')]
class MigrationExecuteQueryTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testExecuteQueryDoesNotPerformWriteOperations(): void
    {
        $nullConnection = new NullConnection();
        $nullConnection->setOriginalConnection(static::getContainer()->get(Connection::class));

        $loader = static::getContainer()->get(MigrationCollectionLoader::class);
        $migrationCollection = $loader->collectAll();

        $exceptions = [];
        try {
            foreach ($migrationCollection as $migrations) {
                /** @var class-string<MigrationStep> $migrationClass */
                foreach ($migrations->getMigrationSteps() as $migrationClass) {
                    $migration = new $migrationClass();
                    $migration->update($nullConnection);
                    $migration->updateDestructive($nullConnection);
                }
            }
        } catch (\Exception $e) {
            if ($e->getMessage() === NullConnection::EXCEPTION_MESSAGE) {
                $exceptions[] = \sprintf('%s Trace: %s', NullConnection::EXCEPTION_MESSAGE, $e->getTraceAsString());
            }
            // ignore error because it is possible that older migrations just don't work on read anymore
        }
        static::assertCount(0, $exceptions, print_r($exceptions, true));
    }
}
