<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Request;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

/**
 * Injects DeviceContext into controllers from the `_device` request attribute.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class DeviceContextValueResolver implements ValueResolverInterface
{
    /**
     * @return iterable<DeviceContext|null>
     */
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        if ($argument->getType() !== DeviceContext::class) {
            return [];
        }
        $context = $request->attributes->get('_device');
        if ($context instanceof DeviceContext) {
            return [$context];
        }
        if ($argument->isNullable()) {
            return [null];
        }

        return [];
    }
}
