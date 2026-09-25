<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Store\Command;

use GuzzleHttp\Exception\ClientException;
use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Store\Services\StoreClient;
use Shopwell\Core\System\SystemConfig\SystemConfigService;
use Shopwell\Core\System\User\UserCollection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 */
#[Package('checkout')]
#[AsCommand(
    name: 'store:login',
    description: 'Login to the store',
)]
class StoreLoginCommand extends Command
{
    /**
     * @param EntityRepository<UserCollection> $userRepository
     */
    public function __construct(
        private readonly StoreClient $storeClient,
        private readonly EntityRepository $userRepository,
        private readonly SystemConfigService $configService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('shopwellId', 'i', InputOption::VALUE_REQUIRED, 'Shopwell ID')
            ->addOption('password', 'p', InputOption::VALUE_REQUIRED, 'Password')
            ->addOption('user', 'u', InputOption::VALUE_REQUIRED, 'User')
            ->addOption('host', 'g', InputOption::VALUE_OPTIONAL, 'License host')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $context = Context::createCLIContext();

        $host = $input->getOption('host');
        if ($host !== null && $host !== '') {
            $this->configService->set('core.store.licenseHost', $host, null, false);
        }

        $shopwellId = $input->getOption('shopwellId');
        $password = $input->getOption('password');
        $user = $input->getOption('user');

        if (!$password) {
            $passwordQuestion = new Question('Enter password');
            $passwordQuestion->setValidator(Validation::createCallable(new NotBlank(message: 'The password cannot be empty')));
            $passwordQuestion->setHidden(true);
            $passwordQuestion->setMaxAttempts(3);

            $password = $io->askQuestion($passwordQuestion);
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('user.username', $user));

        $userId = $this->userRepository->searchIds($criteria, $context)->firstId();

        if ($userId === null) {
            $io->error('User not found');

            return self::FAILURE;
        }

        $userContext = new Context(new AdminApiSource($userId));

        if ($shopwellId === null || $password === null) {
            $io->error('Shopwell ID and password are required.');

            return self::FAILURE;
        }

        try {
            $this->storeClient->loginWithShopwellId($shopwellId, $password, $userContext);
        } catch (ClientException $exception) {
            $io->error(\sprintf('Store login failed: %s', $exception->getMessage()));

            return self::FAILURE;
        }

        $io->success('Successfully logged in.');

        return Command::SUCCESS;
    }
}
