<?php

declare(strict_types=1);

namespace TikTokShopRss\Model;

readonly class BuildResult
{
    public function __construct(
        public int $pagesChanged,
        public State $state,
    ) {
    }
}
