<?php

declare(strict_types=1);

namespace TikTokShopRss\Application\Dto;

readonly class RssConfig
{
    public function __construct(
        public bool $enableContentEncoded = true,
    ) {
    }
}
