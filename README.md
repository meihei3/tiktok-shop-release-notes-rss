# TikTok Shop Developer Documentation RSS Feed

> **Unofficial Project**: This is an **unofficial**, community-maintained RSS feed. It is **not affiliated with, endorsed by, or officially connected to TikTok or ByteDance**. For official documentation, please visit the [TikTok Shop Partner Portal](https://partner.tiktokshop.com/docv2/docs).

An automated RSS feed for TikTok Shop Developer Documentation updates, helping developers stay informed about API changes, new features, and important announcements.

## Live Feed

**Feed URL**: [`https://meihei3.github.io/tiktok-shop-release-notes-rss/rss/changelog.xml`](https://meihei3.github.io/tiktok-shop-release-notes-rss/rss/changelog.xml)

**Website**: [https://meihei3.github.io/tiktok-shop-release-notes-rss/](https://meihei3.github.io/tiktok-shop-release-notes-rss/)

## Features

- Fully automated daily updates at 9:00 AM JST (00:00 UTC)
- Includes keywords like "Coming Soon", "Action Required", "Breaking Change"
- Smart change detection using content hashing
- Only publishes when actual updates are detected

## Quick Start

1. Copy the feed URL: `https://meihei3.github.io/tiktok-shop-release-notes-rss/rss/changelog.xml`
2. Add it to your RSS reader (Feedly, Inoreader, NetNewsWire, etc.)
3. Stay updated automatically

## Local Development

```bash
# Install dependencies
composer install

# Generate RSS feed
composer generate-rss

# Run tests
composer test

# Code quality checks
composer phpstan
composer phpcs
```

## Configuration

Configuration can be done through either a YAML file or environment variables.

### Using YAML Config File (Recommended)

Create or edit `.ttsrss.yaml`:

```yaml
state_file: var/state/tiktok-shop.json

sources:
  - tree_url: "https://partner.tiktokshop.com/api/v1/document/tree?workspace_id=3&aid=359713&locale=ja-JP"
    detail_url_template: "https://partner.tiktokshop.com/api/v1/document/detail?document_id={document_path}&workspace_id=3&aid=359713&locale=ja-JP"
    public_url_template: "https://partner.tiktokshop.com/docv2/page/{document_path}"

channel:
  title: TikTok Shop Dev Updates
  link: https://partner.tiktokshop.com/docv2
  description: Official updates for TikTok Shop developer docs

rss:
  enable_content_encoded: true

limits:
  pages: 50
  items: 30

concurrency: 10

retry:
  attempts: 3
  backoff_initial_ms: 100
  backoff_max_ms: 5000

sleep_between_requests_ms: 100
```

### Using Environment Variables

Alternatively, set environment variables in `.env`:

```env
# TikTok Shop API
WORKSPACE_ID=3
AID=359713
LOCALE=ja-JP

# RSS Feed
CHANNEL_TITLE=TikTok Shop Release Notes
CHANNEL_LINK=https://partner.tiktokshop.com/docv2/docs
CHANNEL_DESCRIPTION=Official TikTok Shop Developer Documentation Updates
CHANNEL_LANGUAGE=ja

# Limits
LIMIT_PAGES=30
LIMIT_ITEMS=30
```

## License

MIT License

## Disclaimer

This is an **unofficial** project created and maintained by the community:

- Not affiliated with TikTok or ByteDance
- Not endorsed or supported by TikTok
- Not an official TikTok product or service
- Community-maintained for developer convenience
- Open source and transparent

For official TikTok Shop resources:
- [Official Documentation](https://partner.tiktokshop.com/docv2/docs)
- [Partner Portal](https://partner.tiktokshop.com/)
