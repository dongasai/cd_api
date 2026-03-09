<?php

namespace App\Services\ChannelAffinity\DTO;

use Illuminate\Http\Request;

readonly class AffinityContext
{
    public function __construct(
        public Request $request,
        public string $model,
        public ?string $groupName = null,
        public ?string $apiKey = null,
        public ?string $clientIp = null,
        public ?string $userAgent = null,
    ) {}

    public static function fromRequest(Request $request, string $model, ?string $groupName = null): self
    {
        $apiKey = $request->attributes->get('api_key');

        return new self(
            request: $request,
            model: $model,
            groupName: $groupName,
            apiKey: $apiKey?->key,
            clientIp: $request->ip(),
            userAgent: $request->userAgent(),
        );
    }
}
