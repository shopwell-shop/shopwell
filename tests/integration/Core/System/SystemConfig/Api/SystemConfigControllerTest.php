<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\System\SystemConfig\Api;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\System\SystemConfig\Api\SystemConfigController;
use Shopwell\Core\System\SystemConfig\Service\ConfigurationService;
use Shopwell\Core\System\SystemConfig\Service\SystemConfigDefinitionService;
use Shopwell\Core\System\SystemConfig\SystemConfigService;
use Shopwell\Core\System\SystemConfig\Validation\SystemConfigValidator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
class SystemConfigControllerTest extends TestCase
{
    use KernelTestBehaviour;

    public function testBatchSaveConfigurationPersistsNestedConfigKeys(): void
    {
        $key = 'core.basicInformation.foo.bar.baz';
        $systemConfigService = static::getContainer()->get(SystemConfigService::class);

        $systemConfigService->delete($key);

        try {
            $response = $this->createController()->batchSaveConfiguration(
                new Request([], [
                    'null' => [
                        $key => 'test-value',
                    ],
                ]),
                Context::createDefaultContext()
            );

            static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
            static::assertSame('test-value', $systemConfigService->get($key));
        } finally {
            $systemConfigService->delete($key);
        }
    }

    private function createController(): SystemConfigController
    {
        return new SystemConfigController(
            static::getContainer()->get(ConfigurationService::class),
            static::getContainer()->get(SystemConfigDefinitionService::class),
            static::getContainer()->get(SystemConfigService::class),
            static::getContainer()->get(SystemConfigValidator::class)
        );
    }
}
