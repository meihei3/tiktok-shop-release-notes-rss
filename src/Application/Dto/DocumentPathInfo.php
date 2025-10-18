<?php

declare(strict_types=1);

namespace TikTokShopRss\Application\Dto;

readonly class DocumentPathInfo
{
    public function __construct(
        public string $path,
        public ?int $updateTime,
    ) {
    }
}
