<?php

namespace Escalated\Laravel\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MacroResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'actions' => $this->actions ?? [],
            // Kept for clients written against 1.9.20, where this was always null.
            'steps' => $this->steps,
            'order' => $this->order,
        ];
    }
}
