<?php

declare(strict_types=1);

namespace TikTokShopRss\Application\Dto;

final readonly class LimitsConfig
{
    public function __construct(
        public int $pages = 300,
        public int $items = 50,
    ) {
    }
}
