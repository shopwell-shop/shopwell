<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Binding;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Event\ShopwellEvent;
use Shopwell\Core\Framework\Log\Package;

/**
 * Business-owned rules may reject a verified release before a binding is saved or activated.
 * The graph is immutable input; listeners reject by throwing their business exception.
 *
 * @final
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class ApprovalBindingValidationEvent implements ShopwellEvent
{
    public function __construct(
        public readonly ApprovalBusinessTypeSpecification $businessType,
        public readonly string $graphJson,
        private readonly Context $context,
    ) {
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
