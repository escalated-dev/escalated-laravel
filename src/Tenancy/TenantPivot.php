<?php

namespace Escalated\Laravel\Tenancy;

use Escalated\Laravel\Concerns\BelongsToTenant;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Relations\Pivot;

class TenantPivot extends Pivot
{
    use BelongsToTenant {
        save as private saveWithTenant;
    }

    public function save(array $options = [])
    {
        $this->assertTenantIdentity();
        if (app(TenantContext::class)->enabled() && $this->exists
            && ($this->isDirty($this->foreignKey) || $this->isDirty($this->relatedKey))) {
            throw new AuthorizationException('Pivot identities cannot be reassigned.');
        }

        return $this->saveWithTenant($options);
    }
}
