<?php

declare(strict_types=1);

namespace TikTokShopRss\Application\Dto;

readonly class SaveRawConfig
{
    public function __construct(
        public bool $enabled = false,
    ) {
    }
}
