<?php

declare(strict_types=1);

namespace TikTokShopRss\Model;

use TikTokShopRss\Application\Dto\ChannelConfig;

readonly class Config
{
    /**
     * @param array<int, Source> $sources
     * @param array<string, mixed> $rss
     * @param array<string, int> $limits
     * @param array<string, int> $retry
     * @param array<string, bool> $saveRaw
     */
    public function __construct(
        public string $stateFile,
        public array $sources,
        public ChannelConfig $channel,
        public array $rss,
        public array $limits,
        public int $concurrency,
        public array $retry,
        public int $sleepBetweenRequestsMs,
        public array $saveRaw,
    ) {
    }
}
