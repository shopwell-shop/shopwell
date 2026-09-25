<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\App\Command;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\App\AppCollection;
use Shopwell\Core\Framework\App\Command\UninstallAppCommand;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[Package('framework')]
class UninstallAppCommandTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository<AppCollection>
     */
    private EntityRepository $appRepository;

    protected function setUp(): void
    {
        $this->appRepository = static::getContainer()->get('app.repository');
    }

    public function testUninstall(): void
    {
        $this->appRepository->create([[
            'name' => 'SwagApp',
            'path' => __DIR__ . '/_fixtures/withPermissions',
            'version' => '0.9.0',
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
        ]], Context::createDefaultContext());

        $commandTester = new CommandTester(static::getContainer()->get(UninstallAppCommand::class));

        $commandTester->execute(['name' => 'SwagApp']);

        static::assertSame(0, $commandTester->getStatusCode());

        static::assertStringContainsString('[OK] App uninstalled successfully.', $commandTester->getDisplay());
    }

    public function testUninstallWithNotFoundApp(): void
    {
        $commandTester = new CommandTester(static::getContainer()->get(UninstallAppCommand::class));

        $commandTester->execute(['name' => 'SwagApp']);

        static::assertSame(1, $commandTester->getStatusCode());

        static::assertStringContainsString('[ERROR] No app with name "SwagApp" installed.', $commandTester->getDisplay());
    }
}
