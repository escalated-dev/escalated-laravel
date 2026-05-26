<?php

namespace Escalated\Laravel\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MobileUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getKey(),
            'name' => (string) $this->name,
            'email' => (string) $this->email,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
