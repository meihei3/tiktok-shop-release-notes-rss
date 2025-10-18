<?php

declare(strict_types=1);

namespace TikTokShopRss\Application\Dto;

final readonly class SaveRawConfig
{
    public function __construct(
        public bool $enabled = false,
    ) {
    }
}
