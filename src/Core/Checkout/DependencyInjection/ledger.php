<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\DependencyInjection;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Checkout\Ledger\RowLock;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    // shared by both ledgers and both purchase settlements: locking one row by its key is the same
    // mechanism whether the row carries points, money, a top-up or a membership order
    $services->set(RowLock::class)
        ->args([
            service(Connection::class),
        ]);
};
