<?php

declare(strict_types=1);

namespace TikTokShopRss\Application\Dto;

final readonly class ChannelConfig
{
    public function __construct(
        public string $title,
        public string $link,
        public string $description,
        public string $language = 'ja',
    ) {
    }
}
