<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Shared\MailFlow\DataProvider;

use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\User\Aggregate\UserRecovery\UserRecoveryCollection;
use Shopwell\Core\System\User\Aggregate\UserRecovery\UserRecoveryDefinition;
use Shopwell\Core\System\User\Aggregate\UserRecovery\UserRecoveryEntity;

/**
 * @internal
 *
 * @extends AbstractProvider<UserRecoveryEntity, UserRecoveryCollection>
 */
#[Package('after-sales')]
class UserRecoveryProvider extends AbstractProvider
{
    public function getEntityName(): string
    {
        return UserRecoveryDefinition::ENTITY_NAME;
    }

    protected function constructCriteria(string $entityId): Criteria
    {
        $criteria = new Criteria([$entityId]);

        $criteria->addAssociation('user');

        return $criteria;
    }
}
