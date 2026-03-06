<?php

namespace App\Services\Provider\Driver;

use App\Services\Provider\DTO\ProviderRequest;
use App\Services\Provider\DTO\ProviderResponse;
use App\Services\Provider\DTO\ProviderStreamChunk;
use Generator;

interface ProviderInterface
{
    public function send(ProviderRequest $request): ProviderResponse;

    public function sendStream(ProviderRequest $request): Generator;

    public function getModels(): array;

    public function getProviderName(): string;

    public function healthCheck(): bool;

    public function isAvailable(): bool;

    public function getLastErrorMessage(): ?string;
}
