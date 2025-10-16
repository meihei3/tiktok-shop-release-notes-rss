<?php

declare(strict_types=1);

namespace TikTokShopRss\Model;

readonly class State
{
    /**
     * @param array<int, SourceState> $sources
     * @param array<int, DocumentItem> $items
     */
    public function __construct(
        public int $version,
        public array $sources,
        public array $items,
    ) {
    }
}
