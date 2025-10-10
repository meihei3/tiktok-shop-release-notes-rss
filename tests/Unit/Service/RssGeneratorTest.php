<?php

declare(strict_types=1);

namespace TikTokShopRss\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use TikTokShopRss\Model\DocumentItem;
use TikTokShopRss\Service\RssGenerator;

class RssGeneratorTest extends TestCase
{
    private RssGenerator $generator;

    protected function setUp(): void
    {
        $templateDir = __DIR__ . '/../../../templates';
        $this->generator = new RssGenerator($templateDir);
    }

    public function testGenerateBasicRss(): void
    {
        $channel = [
            'title' => 'Test Feed',
            'link' => 'https://example.com',
            'description' => 'Test Description',
        ];

        $items = [
            new DocumentItem(
                documentPath: '/docs/test',
                title: 'Test Article',
                description: 'Test article description',
                contentHash: 'abc123',
                pubDate: '2024-01-01T00:00:00+00:00',
                link: 'https://example.com/test',
                contentHtml: '<p>Test content</p>',
            ),
        ];

        $rss = $this->generator->generate($channel, $items);

        $this->assertStringContainsString('<?xml version="1.0" encoding="UTF-8"?>', $rss);
        $this->assertStringContainsString('<rss version="2.0"', $rss);
        $this->assertStringContainsString('<title>Test Feed</title>', $rss);
        $this->assertStringContainsString('<title>Test Article</title>', $rss);
        $this->assertStringContainsString('<link>https://example.com/test</link>', $rss);
    }

    public function testGenerateWithContentEncoded(): void
    {
        $channel = [
            'title' => 'Test Feed',
            'link' => 'https://example.com',
            'description' => 'Test Description',
        ];

        $items = [
            new DocumentItem(
                documentPath: '/docs/test',
                title: 'Test Article',
                description: 'Test description',
                contentHash: 'abc123',
                pubDate: '2024-01-01T00:00:00+00:00',
                link: 'https://example.com/test',
                contentHtml: '<p>Full content HTML</p>',
            ),
        ];

        $rss = $this->generator->generate($channel, $items, true);

        $this->assertStringContainsString('<content:encoded>', $rss);
        $this->assertStringContainsString('&lt;p&gt;Full content HTML&lt;/p&gt;', $rss);
    }

    public function testGenerateWithoutContentEncoded(): void
    {
        $channel = [
            'title' => 'Test Feed',
            'link' => 'https://example.com',
            'description' => 'Test Description',
        ];

        $items = [
            new DocumentItem(
                documentPath: '/docs/test',
                title: 'Test Article',
                description: 'Test description',
                contentHash: 'abc123',
                pubDate: '2024-01-01T00:00:00+00:00',
                link: 'https://example.com/test',
                contentHtml: '<p>Full content HTML</p>',
            ),
        ];

        $rss = $this->generator->generate($channel, $items, false);

        $this->assertStringNotContainsString('<content:encoded>', $rss);
    }

    public function testGenerateWithItemLimit(): void
    {
        $channel = [
            'title' => 'Test Feed',
            'link' => 'https://example.com',
            'description' => 'Test Description',
        ];

        $items = [];
        for ($i = 1; $i <= 100; $i++) {
            $items[] = new DocumentItem(
                documentPath: "/docs/test{$i}",
                title: "Test Article {$i}",
                description: 'Test description',
                contentHash: "hash{$i}",
                pubDate: '2024-01-01T00:00:00+00:00',
                link: "https://example.com/test{$i}",
            );
        }

        $rss = $this->generator->generate($channel, $items, true, 10);

        $itemCount = substr_count($rss, '<item>');
        $this->assertSame(10, $itemCount);
    }

    public function testGenerateWithSpecialCharacters(): void
    {
        $channel = [
            'title' => 'Test Feed',
            'link' => 'https://example.com',
            'description' => 'Test Description',
        ];

        $items = [
            new DocumentItem(
                documentPath: '/docs/test',
                title: 'Test & Article <with> "quotes"',
                description: 'Description with special chars: & < > "',
                contentHash: 'abc123',
                pubDate: '2024-01-01T00:00:00+00:00',
                link: 'https://example.com/test',
            ),
        ];

        $rss = $this->generator->generate($channel, $items);

        $expected = 'Test &amp;amp; Article &amp;lt;with&amp;gt; &amp;quot;quotes&amp;quot;';
        $this->assertStringContainsString($expected, $rss);
    }

    public function testGenerateWithGuid(): void
    {
        $channel = [
            'title' => 'Test Feed',
            'link' => 'https://example.com',
            'description' => 'Test Description',
        ];

        $items = [
            new DocumentItem(
                documentPath: '/docs/test',
                title: 'Test Article',
                description: 'Test description',
                contentHash: 'abcdef123456',
                pubDate: '2024-01-01T00:00:00+00:00',
                link: 'https://example.com/test',
            ),
        ];

        $rss = $this->generator->generate($channel, $items);

        $this->assertStringContainsString('<guid isPermaLink="false">/docs/test#abcdef12</guid>', $rss);
    }

    public function testGenerateWithValidRfc822Date(): void
    {
        $channel = [
            'title' => 'Test Feed',
            'link' => 'https://example.com',
            'description' => 'Test Description',
        ];

        $items = [
            new DocumentItem(
                documentPath: '/docs/test',
                title: 'Test Article',
                description: 'Test description',
                contentHash: 'abc123',
                pubDate: '2024-01-01T12:00:00+00:00',
                link: 'https://example.com/test',
            ),
        ];

        $rss = $this->generator->generate($channel, $items);

        $pattern = '/<pubDate>[A-Z][a-z]{2}, \d{2} [A-Z][a-z]{2} \d{4} '
            . '\d{2}:\d{2}:\d{2} [+-]\d{4}<\/pubDate>/';
        $this->assertMatchesRegularExpression($pattern, $rss);
    }
}
