<?php declare(strict_types=1);

namespace Shopwell\Core\Test\Stub\DataAbstractionLayer;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommandQueue;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;

/**
 * @final
 */
class StaticEntityWriterGateway implements EntityWriteGatewayInterface
{
    public function prefetchExistences(WriteParameterBag $parameterBag): void
    {
    }

    public function getExistence(EntityDefinition $definition, array $primaryKey, array $data, WriteCommandQueue $commandQueue): EntityExistence
    {
        return new EntityExistence($definition->getEntityName(), $primaryKey, false, false, false, []);
    }

    public function execute(array $commands, WriteContext $context): void
    {
    }
}
