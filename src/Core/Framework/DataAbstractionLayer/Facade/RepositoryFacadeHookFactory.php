<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\DataAbstractionLayer\Facade;

use Shopwell\Core\Framework\Api\Acl\AclCriteriaValidator;
use Shopwell\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Script\AppContextCreator;
use Shopwell\Core\Framework\Script\Execution\Awareness\HookServiceFactory;
use Shopwell\Core\Framework\Script\Execution\Hook;
use Shopwell\Core\Framework\Script\Execution\Script;

/**
 * @internal
 */
#[Package('framework')]
class RepositoryFacadeHookFactory extends HookServiceFactory
{
    /**
     * @internal
     */
    public function __construct(
        private readonly DefinitionInstanceRegistry $registry,
        private readonly AppContextCreator $appContextCreator,
        private readonly RequestCriteriaBuilder $criteriaBuilder,
        private readonly AclCriteriaValidator $criteriaValidator
    ) {
    }

    public function factory(Hook $hook, Script $script): RepositoryFacade
    {
        return new RepositoryFacade(
            $this->registry,
            $this->criteriaBuilder,
            $this->criteriaValidator,
            $this->appContextCreator->getAppContext($hook, $script)
        );
    }

    public function getName(): string
    {
        return 'repository';
    }
}
