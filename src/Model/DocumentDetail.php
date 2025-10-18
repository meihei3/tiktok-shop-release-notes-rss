<?php

declare(strict_types=1);

namespace TikTokShopRss\Model;

readonly class DocumentDetail
{
    public function __construct(
        public string $title,
        public string $content,
        public string $description,
        public ?int $updateTime,
    ) {
    }
}
