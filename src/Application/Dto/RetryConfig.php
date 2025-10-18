<?php

declare(strict_types=1);

namespace TikTokShopRss\Application\Dto;

readonly class RetryConfig
{
    public function __construct(
        public int $maxAttempts = 3,
        public int $delayMs = 1000,
    ) {
    }
}
