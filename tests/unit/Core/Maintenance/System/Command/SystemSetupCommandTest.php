<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Maintenance\System\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Maintenance\System\Command\SystemSetupCommand;
use Shopwell\Core\Test\Annotation\DisabledFeatures;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\ApplicationTester;
use Symfony\Component\Dotenv\Command\DotenvDumpCommand;
use Symfony\Component\Dotenv\Dotenv;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SystemSetupCommand::class)]
class SystemSetupCommandTest extends TestCase
{
    protected function tearDown(): void
    {
        @unlink(__DIR__ . '/.env');
        @unlink(__DIR__ . '/symfony.lock');
        @unlink(__DIR__ . '/.env.local.php');
    }

    public function testEnvFileGeneration(): void
    {
        $args = [
            'command' => 'system:setup',
            '--app-env' => 'test',
            '--app-url' => 'https://example.com',
            '--database-url' => 'mysql://localhost:3306/shopwell',
            '--es-hosts' => 'localhost:9200',
            '--es-enabled' => '1',
            '--es-indexing-enabled' => '1',
            '--es-index-prefix' => 'shopwell',
            '--admin-es-hosts' => 'localhost:9200',
            '--admin-es-index-prefix' => 'shopwell-admin',
            '--admin-es-enabled' => '1',
            '--admin-es-refresh-indices' => '1',
            '--http-cache-enabled' => '1',
            '--cdn-strategy' => 'id',
            '--blue-green' => '1',
            '--mailer-url' => 'smtp://localhost:25',
            '--composer-home' => __DIR__,
        ];

        $tester = $this->getApplicationTester();

        $tester->run($args, ['interactive' => false]);

        $tester->assertCommandIsSuccessful();

        static::assertFileExists(__DIR__ . '/.env');
        static::assertFileDoesNotExist(__DIR__ . '/.env.local.php');

        $envContent = file_get_contents(__DIR__ . '/.env');
        static::assertIsString($envContent);
        $env = (new Dotenv())->parse($envContent);

        static::assertArrayHasKey('APP_SECRET', $env);
        static::assertArrayHasKey('INSTANCE_ID', $env);
        unset($env['APP_SECRET'], $env['INSTANCE_ID'], $env['DATABASE_SSL_DONT_VERIFY_SERVER_CERT']);
        static::assertSame([
            'APP_ENV' => 'test',
            'APP_URL' => 'https://example.com',
            'DATABASE_URL' => 'mysql://localhost:3306/shopwell',
            'OPENSEARCH_URL' => 'localhost:9200',
            'SHOPWELL_ES_ENABLED' => '1',
            'SHOPWELL_ES_INDEXING_ENABLED' => '1',
            'SHOPWELL_ES_INDEX_PREFIX' => 'shopwell',
            'ADMIN_OPENSEARCH_URL' => 'localhost:9200',
            'SHOPWELL_ADMIN_ES_INDEX_PREFIX' => 'shopwell-admin',
            'SHOPWELL_ADMIN_ES_ENABLED' => '1',
            'SHOPWELL_ADMIN_ES_REFRESH_INDICES' => '1',
            'SHOPWELL_HTTP_CACHE_ENABLED' => '1',
            'SHOPWELL_CDN_STRATEGY_DEFAULT' => 'id',
            'BLUE_GREEN_DEPLOYMENT' => '1',
            'MAILER_DSN' => 'smtp://localhost:25',
            'COMPOSER_HOME' => __DIR__,
        ], $env);
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed without replacement
     */
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testEnvFileGenerationWithHttpDefaultTtl(): void
    {
        $args = [
            'command' => 'system:setup',
            '--http-cache-ttl' => '7201',
        ];

        $tester = $this->getApplicationTester();

        $tester->run($args, ['interactive' => false]);

        $tester->assertCommandIsSuccessful();

        static::assertFileExists(__DIR__ . '/.env');

        $envContent = file_get_contents(__DIR__ . '/.env');
        static::assertIsString($envContent);
        $env = (new Dotenv())->parse($envContent);

        static::assertArrayHasKey('SHOPWELL_HTTP_DEFAULT_TTL', $env);
        static::assertSame('7201', $env['SHOPWELL_HTTP_DEFAULT_TTL']);
    }

    public function testEnvFileGenerationWithDumpEnv(): void
    {
        $args = [
            'command' => 'system:setup',
            '--app-env' => 'test',
            '--app-url' => 'https://example.com',
            '--database-url' => 'mysql://localhost:3306/shopwell',
            '--es-hosts' => 'localhost:9200',
            '--es-enabled' => '1',
            '--es-indexing-enabled' => '1',
            '--es-index-prefix' => 'shopwell',
            '--admin-es-hosts' => 'localhost:9200',
            '--admin-es-index-prefix' => 'shopwell-admin',
            '--admin-es-enabled' => '1',
            '--admin-es-refresh-indices' => '1',
            '--http-cache-enabled' => '1',
            '--cdn-strategy' => 'id',
            '--blue-green' => '1',
            '--mailer-url' => 'smtp://localhost:25',
            '--composer-home' => __DIR__,
            '--dump-env' => true,
        ];

        $tester = $this->getApplicationTester();

        $tester->run($args, ['interactive' => false]);

        $tester->assertCommandIsSuccessful();

        static::assertFileExists(__DIR__ . '/.env');
        static::assertFileExists(__DIR__ . '/.env.local.php');

        $envContent = file_get_contents(__DIR__ . '/.env');
        static::assertIsString($envContent);
        $env = (new Dotenv())->parse($envContent);

        /** @phpstan-ignore require.fileNotFound (Although the existence of the file is checked above, PHPStan will only consider files, that always exist. See https://github.com/phpstan/phpstan/issues/12417) */
        $envLocal = require __DIR__ . '/.env.local.php';
        static::assertSame($env, $envLocal);
    }

    public function testSymfonyFlexGeneratesWarning(): void
    {
        $args = [
            'command' => 'system:setup',
            '-v' => true,
            '--app-env' => 'test',
            '--app-url' => 'https://example.com',
            '--database-url' => 'mysql://localhost:3306/shopwell',
            '--es-hosts' => 'localhost:9200',
            '--es-enabled' => '1',
            '--es-indexing-enabled' => '1',
            '--es-index-prefix' => 'shopwell',
            '--http-cache-enabled' => '1',
            '--cdn-strategy' => 'id',
            '--blue-green' => '1',
            '--mailer-url' => 'smtp://localhost:25',
            '--composer-home' => __DIR__,
        ];

        touch(__DIR__ . '/symfony.lock');

        $tester = $this->getApplicationTester();

        $tester->run($args, ['interactive' => false, 'verbosity' => OutputInterface::VERBOSITY_DEBUG]);

        $tester->assertCommandIsSuccessful();

        static::assertStringContainsString('It looks like you have installed Shopwell with Symfony Flex', $tester->getDisplay());
    }

    private function getApplicationTester(): ApplicationTester
    {
        $dumpCommand = new DotenvDumpCommand(__DIR__);

        $application = new Application();
        $application->setAutoExit(false);
        $application->addCommand(new SystemSetupCommand(__DIR__, $dumpCommand));

        $application->addCommand($dumpCommand);

        return new ApplicationTester($application);
    }
}
