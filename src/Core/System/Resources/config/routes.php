<?php declare(strict_types=1);

use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * @codeCoverageIgnore
 */
return static function (RoutingConfigurator $routes): void {
    $routes->import('../../Consent/Api/**/*Controller.php', 'attribute');
    $routes->import('../../User/Api/**/*Controller.php', 'attribute');
    $routes->import('../../CustomEntity/Api/*Controller.php', 'attribute');
    $routes->import('../../Snippet/**/*Controller.php', 'attribute');
    $routes->import('../../Snippet/SalesChannel/**/*Route.php', 'attribute');
    $routes->import('../../CustomField/**/*Controller.php', 'attribute');
    $routes->import('../../SystemConfig/**/*Controller.php', 'attribute');
    $routes->import('../../SystemConfig/SalesChannel/**/*Route.php', 'attribute');
    $routes->import('../../NumberRange/**/*Controller.php', 'attribute');
    $routes->import('../../SalesChannel/SalesChannel/**/*Controller.php', 'attribute');
    $routes->import('../../SalesChannel/SalesChannel/**/*Route.php', 'attribute');
    $routes->import('../../SalesChannel/File/**/*Controller.php', 'attribute');
    $routes->import('../../StateMachine/Api/*Controller.php', 'attribute');
    $routes->import('../../Currency/SalesChannel/**/*Route.php', 'attribute');
    $routes->import('../../Language/SalesChannel/**/*Route.php', 'attribute');
    $routes->import('../../Dictionary/SalesChannel/**/*Route.php', 'attribute');
    $routes->import('../../Country/SalesChannel/**/*Route.php', 'attribute');

    // the generic payment gateway is a plain json api of its own, see PaymentApiRouteScope
    $routes->import('../../Payment/**/*Controller.php', 'attribute');
    $routes->import('../../Sms/Api/*Controller.php', 'attribute');
};
