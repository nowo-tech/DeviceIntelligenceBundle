<?php

declare(strict_types=1);

use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * Attribute routes for Device Intelligence. Hosts may import this file or rely on the Flex recipe.
 */
return static function (RoutingConfigurator $routes): void {
    $routes->import('../../Controller/', 'attribute');
};
