<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\User;

interface UserIdentifierResolverInterface
{
    public function resolve(object $user): UserIdentifier;
}
