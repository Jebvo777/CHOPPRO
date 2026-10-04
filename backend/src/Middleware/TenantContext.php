<?php

declare(strict_types=1);

namespace Choppro\Middleware;

final class TenantContext
{
    private ?string $tenantId = null;

    public function set(string $tenantId): void
    {
        $this->tenantId = $tenantId;
    }

    public function id(): string
    {
        if ($this->tenantId === null) {
            throw new \RuntimeException('Tenant context is not initialized');
        }

        return $this->tenantId;
    }
}
