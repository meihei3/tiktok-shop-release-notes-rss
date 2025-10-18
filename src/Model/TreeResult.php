<?php

declare(strict_types=1);

namespace TikTokShopRss\Model;

readonly class TreeResult
{
    /**
     * @param array<int, array<string, mixed>> $documentTree
     */
    public function __construct(
        public bool $notModified,
        public array $documentTree,
        public ?string $etag,
        public ?string $lastModified,
    ) {
    }
}
