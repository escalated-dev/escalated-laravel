<?php

namespace Escalated\Laravel\Tenancy;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantBelongsTo extends BelongsTo
{
    use GuardsHostRelation;

    public function getResults()
    {
        $this->assertTenantParent();

        return $this->visibleHostResult(parent::getResults());
    }

    public function getEager()
    {
        return parent::getEager()->filter(fn ($model) => $this->visibleHostResult($model) !== null)->values();
    }
}
