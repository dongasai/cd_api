<?php

namespace App\Services\ChannelAffinity\Exceptions;

use Exception;

class AffinityException extends Exception
{
    public static function ruleNotFound(string $name): self
    {
        return new self("Affinity rule not found: {$name}");
    }

    public static function invalidKeySource(string $type): self
    {
        return new self("Invalid key source type: {$type}");
    }

    public static function channelNotFound(int $channelId): self
    {
        return new self("Channel not found: {$channelId}");
    }
}
