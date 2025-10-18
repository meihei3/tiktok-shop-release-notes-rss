<?php

declare(strict_types=1);

namespace TikTokShopRss\Application\Dto;

use TikTokShopRss\Model\State;

readonly class BuildResult
{
    public function __construct(
        public int $pagesChanged,
        public State $state,
    ) {
    }
}
