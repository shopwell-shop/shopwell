<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\App\Lifecycle;

use Shopwell\Core\Framework\App\AppEntity;
use Shopwell\Core\Framework\App\AppException;
use Shopwell\Core\Framework\App\AppStorage;
use Shopwell\Core\Framework\App\Lifecycle\Parameters\AppInstallParameters;
use Shopwell\Core\Framework\App\Lifecycle\Parameters\AppUpdateParameters;
use Shopwell\Core\Framework\App\Manifest\Manifest;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Plugin\Exception\DecorationPatternException;

/**
 * @internal
 */
#[Package('framework')]
class AppLifecycle extends AbstractAppLifecycle
{
    public function __construct(
        private readonly AppManager $appManager,
        private readonly AppStorage $appStorage,
    ) {
    }

    public function getDecorated(): AbstractAppLifecycle
    {
        throw new DecorationPatternException(self::class);
    }

    public function install(Manifest $manifest, AppInstallParameters $parameters, Context $context): void
    {
        $this->appManager->install($manifest, $parameters, $context);
    }

    public function activate(string $appId, Context $context): void
    {
        $this->appManager->activate($this->loadApp($appId, $context), $context);
    }

    public function deactivate(string $appId, Context $context): void
    {
        $this->appManager->deactivate($this->loadApp($appId, $context), $context);
    }

    public function update(Manifest $manifest, AppUpdateParameters $parameters, array $app, Context $context): void
    {
        $this->appManager->update($manifest, $parameters, $this->loadApp($app['id'], $context), $context);
    }

    public function uninstall(string $appName, array $app, Context $context, bool $keepUserData = false): void
    {
        $this->appManager->uninstall($this->loadApp($app['id'], $context), $context, $keepUserData);
    }

    private function loadApp(string $id, Context $context): AppEntity
    {
        $app = $this->appStorage->findById($id, $context);
        if ($app === null) {
            throw AppException::notFound($id);
        }

        return $app;
    }
}
