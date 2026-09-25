<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Checkout\Cart\Facade;

use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Checkout\Cart\Hook\CartAware;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Script\Execution\Awareness\SalesChannelContextAwareTrait;
use Shopwell\Core\Framework\Script\Execution\Hook;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
#[Package('checkout')]
class CartTestHook extends Hook implements CartAware
{
    use SalesChannelContextAwareTrait;

    public IdsCollection $ids;

    /**
     * @var list<class-string<object>>
     */
    private static array $serviceIds;

    /**
     * @param list<class-string<object>> $serviceIds
     */
    public function __construct(
        private readonly string $name,
        private readonly Cart $cart,
        SalesChannelContext $context,
        IdsCollection $ids,
        array $serviceIds = []
    ) {
        parent::__construct($context->getContext());
        $this->salesChannelContext = $context;
        self::$serviceIds = $serviceIds;
        $this->ids = $ids;
    }

    public function getCart(): Cart
    {
        return $this->cart;
    }

    /**
     * @return list<class-string<object>>
     */
    public static function getServiceIds(): array
    {
        return self::$serviceIds;
    }

    public function getName(): string
    {
        return $this->name;
    }
}
