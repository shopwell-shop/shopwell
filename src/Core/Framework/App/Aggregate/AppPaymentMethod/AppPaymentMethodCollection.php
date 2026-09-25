<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\App\Aggregate\AppPaymentMethod;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 *
 * @extends EntityCollection<AppPaymentMethodEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class AppPaymentMethodCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return AppPaymentMethodEntity::class;
    }
}
