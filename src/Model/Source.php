<?php

declare(strict_types=1);

namespace TikTokShopRss\Model;

readonly class Source
{
    public function __construct(
        public string $treeUrl,
        public string $detailUrlTemplate,
        public string $publicUrlTemplate,
    ) {
    }
}
