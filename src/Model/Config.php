<?php

declare(strict_types=1);

namespace TikTokShopRss\Model;

use TikTokShopRss\Application\Dto\ChannelConfig;
use TikTokShopRss\Application\Dto\LimitsConfig;
use TikTokShopRss\Application\Dto\RetryConfig;
use TikTokShopRss\Application\Dto\RssConfig;
use TikTokShopRss\Application\Dto\SaveRawConfig;

readonly class Config
{
    /**
     * @param array<int, Source> $sources
     */
    public function __construct(
        public string $stateFile,
        public array $sources,
        public ChannelConfig $channel,
        public RssConfig $rss,
        public LimitsConfig $limits,
        public int $concurrency,
        public RetryConfig $retry,
        public int $sleepBetweenRequestsMs,
        public SaveRawConfig $saveRaw,
    ) {
    }
}
