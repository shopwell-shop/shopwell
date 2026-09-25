<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Store\Command;

use GuzzleHttp\Exception\ClientException;
use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Plugin\PluginCollection;
use Shopwell\Core\Framework\Plugin\PluginEntity;
use Shopwell\Core\Framework\Plugin\PluginLifecycleService;
use Shopwell\Core\Framework\Plugin\PluginManagementService;
use Shopwell\Core\Framework\Store\Services\StoreClient;
use Shopwell\Core\Framework\Store\StoreException;
use Shopwell\Core\System\User\UserCollection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 */
#[Package('checkout')]
#[AsCommand(
    name: 'store:download',
    description: 'Downloads a plugin from the store',
)]
class StoreDownloadCommand extends Command
{
    /**
     * @param EntityRepository<PluginCollection> $pluginRepo
     * @param EntityRepository<UserCollection> $userRepository
     */
    public function __construct(
        private readonly StoreClient $storeClient,
        private readonly EntityRepository $pluginRepo,
        private readonly PluginManagementService $pluginManagementService,
        private readonly PluginLifecycleService $pluginLifecycleService,
        private readonly EntityRepository $userRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('pluginName', 'p', InputOption::VALUE_REQUIRED, 'Name of plugin')
            ->addOption('language', 'l', InputOption::VALUE_OPTIONAL, 'Language')
            ->addOption('user', 'u', InputOption::VALUE_OPTIONAL, 'User')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $context = Context::createCLIContext();

        $pluginName = (string) $input->getOption('pluginName');
        $user = $input->getOption('user');

        $context = $this->getUserContextFromInput($user, $context);

        $this->validatePluginIsNotManagedByComposer($pluginName, $context);

        try {
            $data = $this->storeClient->getDownloadDataForPlugin($pluginName, $context);
        } catch (ClientException $exception) {
            throw StoreException::storeError($exception);
        }

        $this->pluginManagementService->downloadStorePlugin($data, $context);

        $plugin = $this->getPluginFromInput($pluginName, $context);

        if ($plugin === null) {
            // don't update plugins that are not installed
            return self::SUCCESS;
        }

        if ($plugin->getUpgradeVersion()) {
            $this->pluginLifecycleService->updatePlugin($plugin, $context);
        }

        return self::SUCCESS;
    }

    private function getUserContextFromInput(?string $userName, Context $context): Context
    {
        if (!$userName) {
            return $context;
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('user.username', $userName));

        $userEntity = $this->userRepository->search($criteria, $context)->getEntities()->first();
        if ($userEntity === null) {
            return $context;
        }

        return Context::createCLIContext(new AdminApiSource($userEntity->getId()));
    }

    private function validatePluginIsNotManagedByComposer(string $pluginName, Context $context): void
    {
        $plugin = $this->getPluginFromInput($pluginName, $context);

        if ($plugin === null) {
            return;
        }

        if ($plugin->getManagedByComposer() && !$plugin->isLocatedInCustomPluginDirectory()) {
            throw StoreException::cannotDeleteManaged($pluginName);
        }
    }

    private function getPluginFromInput(string $pluginName, Context $context): ?PluginEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('plugin.name', $pluginName));

        return $this->pluginRepo->search($criteria, $context)->getEntities()->first();
    }
}
