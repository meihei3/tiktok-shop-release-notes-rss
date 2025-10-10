<?php

declare(strict_types=1);

namespace TikTokShopRss\Model;

readonly class SourceState
{
    public function __construct(
        public string $url,
        public ?string $etag,
        public ?string $lastModified,
        public string $contentHash,
        public string $lastSeenAt,
    ) {
    }
}
