# TikTok Shop 開発ドキュメントRSSビルダー — Requirements

## 目的

TikTok Shop の開発ドキュメント更新を定期取得して差分から RSS を生成するコマンドを提供する 情報収集の自動化と通知フローへの接続を容易にする

## スコープ

- Symfony CLI コマンドでの実行
- 公式ドキュメントの更新検知と変換
- RSS 2.0 形式のフィード生成
- 差分ストアと重複防止
- 運用に必要なログとメトリクス出力

## 非スコープ

- 非公開資料や有料記事のクロール
- スクレイピング禁止領域の突破
- RSS 以外の Atom/JSON Feed の正式対応（拡張余地としては残す）

## 用語

- **ソース**: TikTok Shop 開発ドキュメントの公開ページ群
- **エントリ**: ページ単位またはセクション単位の更新イベント
- **フィード**: 生成した RSS 2.0 ドキュメント

## コマンド仕様

- 実行名: `bin/console tts:rss:build`
- 主オプション:
  - `--since=DATETIME` ISO8601 省略可 差分基準時刻
  - `--full` 差分ではなく全量スキャン
  - `--output=PATH` 出力先ファイル default: `var/rss/tiktok-shop.xml`
  - `--docs-dir=PATH` GitHub Pages 用の公開ディレクトリ default: `docs/`
  - `--output-basename=STR` `docs/` 以下に出力するファイル名 既定 `index.xml`
  - `--state=PATH` 状態ファイルのパス default: `var/state/tiktok-shop.json`
  - `--dry-run` 出力せず検出件数だけ表示 非ゼロの exit code は使わない
- Exit Code:
  - 0 成功
  - 2 入力不正
  - 3 ソース到達不可
  - 4 出力/永続化失敗
  - 5 想定外エラー

## 入出力

- 入力: ソース URL 群 設定ファイル `.ttsrss.yaml` と環境変数
- 出力: RSS 2.0 XML 1 ファイル または標準出力 `--output=-`
- 状態: JSON ファイルで永続化 `var/state/tiktok-shop.json` 既定 形式は後述のスキーマに準拠（`items[].description` を含む）
- 追加出力（オプション）: ツリー JSON と detail 生 JSON の保存（各 `var/raw/` 配下）
- GitHub Pages 用出力: `--docs-dir` 指定時は `<docs-dir>/<output-basename>`（既定 `docs/index.xml`）にも複製保存

## 設定読み込み優先度

CLI オプション > 環境変数 > プロジェクト設定ファイル > デフォルト

CLI オプション > 環境変数 > プロジェクト設定ファイル > デフォルト

## コンフィグ例 `.ttsrss.yaml`

```yaml
state_file: var/state/tiktok-shop.json
save_raw:
  tree: true
  detail: true
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
  pages: 300
  items: 50
concurrency: 10
retry:
  attempts: 3
  backoff_initial_ms: 100
  backoff_max_ms: 5000
sleep_between_requests_ms: 100
```

## API仕様（実装詳細）

### Tree API
- **エンドポイント**: `{tree_url}` (例: `https://partner.tiktokshop.com/api/v1/document/tree?workspace_id=3&aid=359713&locale=ja-JP`)
- **HTTPメソッド**: GET
- **条件付きリクエスト**: `If-None-Match`, `If-Modified-Since` ヘッダーをサポート
- **レスポンス形式**:
  ```json
  {
    "data": {
      "document_tree": [
        {
          "document_path": "string (例: \"1234567\")",
          "update_time": number (UNIXタイムスタンプ, nullable),
          "children": [ /* 再帰的な構造 */ ]
        }
      ]
    }
  }
  ```
- **レスポンスヘッダー**: `ETag`, `Last-Modified`
- **ステータスコード**:
  - `200`: 成功（本文あり）
  - `304`: Not Modified（本文なし）
  - `4xx/5xx`: エラー

### Detail API
- **エンドポイント**: `{detail_url_template}` の `{document_path}` を置換 (例: `https://partner.tiktokshop.com/api/v1/document/detail?document_id=1234567&workspace_id=3&aid=359713&locale=ja-JP`)
- **HTTPメソッド**: GET
- **レスポンス形式**:
  ```json
  {
    "data": {
      "title": "string",
      "content": "string (HTML)",
      "description": "string",
      "update_time": number (UNIXタイムスタンプ, nullable)
    }
  }
  ```
- **ステータスコード**:
  - `200`: 成功
  - `4xx/5xx`: エラー

### document_path の定義
- **形式**: 文字列（例: `"1234567"`, `"api/overview"`）
- **取得元**: Tree API の各ノードの `document_path` フィールド
- **用途**:
  - Detail API の URL パラメータとして使用（`{document_path}` を置換）
  - 状態ファイル内の一意識別子（主キー）として使用
  - RSS の `guid` 生成に使用

## 更新検知の要件

1. **Tree API**: HTTP 条件付きリクエストを使用 (`If-None-Match`, `If-Modified-Since`)
   - 前回の `ETag` と `Last-Modified` を保存し、次回リクエスト時にヘッダーに含める
   - `304 Not Modified` の場合は detail 取得をスキップ
2. **Detail API**: コンテンツハッシュ比較で変更検知
   - `content` フィールドの `sha256` ハッシュを計算
   - 前回保存されたハッシュと比較し、一致すれば更新なしと判定
3. **正規化ルール**（現在の実装）:
   - コンテンツハッシュ: `content` フィールドをそのまま `sha256` でハッシュ化
   - 空白正規化やHTMLサニタイズは行わない（将来の拡張余地として残す）
4. 差分はページごとに最新のみ保持 履歴はオプション

## RSS 生成の要件

1. RSS 2.0 準拠 `<channel>` `<item>` を正しく構成
2. `guid` はページ URL + コンテンツハッシュで安定生成
3. `pubDate` は更新検知時刻 UTC で RFC822 形式
4. `description` は **tree ではなく detail の値** を優先使用（取得できない場合は `content_html` からテキスト要約を生成） 500 文字上限 HTML サニタイズ
5. `<link>` は絶対 URL に解決
6. `<content:encoded>`（content namespace: `http://purl.org/rss/1.0/modules/content/`）を出力し detail の `content_html` をサニタイズの上で埋め込む
7. `channel` メタ: `title` `link` `description` `language` `lastBuildDate`
8. 件数上限 `--limit=N` 既定 50 件

## エラーハンドリング

- ネットワーク
  - 再試行: 指数バックオフ 100ms〜5s 3 回
  - ステータス 429/5xx は再試行対象
- 解析失敗
  - エントリスキップして警告ログ
- 永続層（JSON ファイル）
  - 書込はテンポラリへの出力→`rename` でアトミック更新
  - 併用プロセス対策としてファイルロック（排他）を取得
  - 破損検出時は自動バックアップから復旧を試み 失敗したら出力を中断し Exit 4

## 永続化フォーマット

- 既定ファイル: `var/state/tiktok-shop.json`
- 識別子ポリシー: \`\`\*\* を永続化上の ID（ナチュラルキー）として扱う\*\*。すべての参照・更新・重複排除は `document_path` をキーに行う。
- 構造: ルートはオブジェクト 主要キーは以下
  - `version`: スキーマバージョン 例 `2`
  - `sources`: ソースごとの状態配列
    - `url`: 文字列
    - `etag`: 文字列 null 可
    - `lastModified`: RFC1123 文字列 null 可
    - `contentHash`: sha256 文字列（detail.`content_html` 正規化後のハッシュ）
    - `lastSeenAt`: ISO8601 文字列
  - `items`: 生成済み item のキャッシュ配列（\*\*主キーは \*\*\`\`）
    - `document_path`: 文字列（**ID**）
    - `title`: 文字列
    - `description`: 文字列（detail 由来 フォールバックは要約）
    - `contentHash`: 文字列（sha256）
    - `pubDate`: ISO8601 文字列（抽出日付 or 検知時刻）
- バージョニング: 互換性のない変更時は `version` をインクリメントしてマイグレーションを実施（v1→v2 では `items[].description` を追加）

## バリデーション

- 渡された URL が http/https かを検証
- `--since` は過去日時のみ許可 未来はエラー
- `--output` 親ディレクトリ存在チェック

## 監視/可観測性

- `stdout` に実行サマリ JSON ログ `--json`
- 収集指標
  - `crawl.pages_total`
  - `crawl.pages_changed`
  - `crawl.retry_count`
  - `rss.items_emitted`
  - `duration_ms`
  - `state.file_write_ms`
  - `state.file_size_bytes`

## セキュリティ

- robots.txt と `nofollow` を尊重
- ヘッダー `User-Agent` を識別可能に設定
- 認証不要領域のみ対象 認証が必要な場合は範囲外

## 運用要件

- cron 例: `0 * * * * /path/app/bin/console tts:rss:build --since='-70 minutes'`
- 週次フルスキャンの例: `0 3 * * 0 ... --full`

## アーキテクチャ（実装済み）

本プロジェクトは**レイヤードアーキテクチャ**と**ポート&アダプターパターン**を採用:

### ディレクトリ構造
```
src/
├── Application/          # アプリケーション層
│   ├── Dto/             # アプリケーション層のDTO (7クラス)
│   ├── Port/            # ポート（Interface定義）
│   └── UseCase/         # ビジネスロジック (BuildRssUseCase)
├── Model/               # ドメインモデル (5 ValueObjects)
│   ├── Config, DocumentItem, Source, SourceState, State
├── Infrastructure/      # インフラ層（外部システム接続）
│   ├── Http/
│   │   ├── DocumentFetcher.php   # HTTP通信実装
│   │   └── Dto/                  # Infrastructure層のDTO (3クラス)
│   └── Persistence/
│       └── StateManager.php       # 状態永続化実装
├── Service/             # サービス層
│   ├── ConfigLoader.php           # 設定読み込み
│   └── RssGenerator.php           # RSS生成（Twig使用）
└── Command/             # コマンド層
    └── BuildRssCommand.php        # Symfony Console コマンド
```

### 設計原則
- **依存性逆転**: 上位層（Application）は下位層（Infrastructure）の抽象（Port）に依存
- **型安全性**: PHPStan level max、全DTO/ValueObjectは `final readonly class`
- **境界の明確化**: ArrayShapes禁止、全て型定義されたDTOで境界を越える
- **不変性**: readonly classによりValueObject/DTOの不変性を保証

### 品質保証
- **PHPUnit**: 23 tests, 92 assertions
- **PHPStan**: Level max (最高レベルの静的解析)
- **PHP_CodeSniffer**: PSR-12準拠
- **CI**: GitHub Actions でPR時に自動チェック

## 配布/公開（GitHub Pages）

- 方式: `docs/` ディレクトリを Pages の公開ルートに設定（Settings → Pages → Deploy from a branch → Branch: `main`, Folder: `/docs`）
- 生成物: `docs/index.xml`（RSS） 必要なら `docs/feed.xml` や `docs/index.html`（簡易説明）も出力可
- ベース URL 例: `https://<github_id>.github.io/<repo>/index.xml`
- 参考: `meihei3/square-release-notes-rss` の構成に準拠（ディレクトリと公開設定）

### CI/CD（実装済み: コード品質チェック）

現在実装されているCI（`.github/workflows/ci.yml`）:
```yaml
name: CI
on:
  pull_request:
    branches: [main]
  push:
    branches: [main]
jobs:
  code-quality:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.4'
      - run: composer install
      - run: composer test      # PHPUnit
      - run: composer phpstan   # 静的解析
      - run: composer phpcs     # コードスタイル
```

### CI/CD（参考: RSS自動ビルド - 未実装）

```yaml
name: build-rss
on:
  schedule:
    - cron: "0 2 * * *"   # JST 11:00 実行など適宜
  workflow_dispatch:
permissions:
  contents: write
jobs:
  build:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.4'
      - run: composer install --no-interaction --no-progress --prefer-dist
      - name: Build RSS into docs/
        run: |
          bin/console tts:rss:build --since='-25 hours' --docs-dir=docs --output-basename=index.xml
      - name: Commit if changed
        run: |
          git config user.name github-actions
          git config user.email github-actions@github.com
          git add docs/index.xml var/state/tiktok-shop.json || true
          git diff --staged --quiet || git commit -m "chore(rss): update $(date -u +%FT%TZ)" && git push
```

## 実装状況サマリー

### ✅ 実装済み機能
- ✅ コマンド実装 (`tts:rss:build`)
- ✅ Tree API からドキュメント一覧取得（HTTP条件付きリクエスト対応）
- ✅ Detail API から詳細取得
- ✅ RSS 2.0 生成（`<content:encoded>` 含む）
- ✅ 状態ファイル永続化（JSON）
- ✅ コンテンツハッシュ比較による差分検知
- ✅ GitHub Pages 用出力（`--docs-dir`）
- ✅ dry-run モード
- ✅ 標準出力モード（`--output=-`）
- ✅ JSON サマリ出力（`--json`）
- ✅ レイヤードアーキテクチャ実装
- ✅ PHPStan level max 対応
- ✅ CI/CD（コード品質チェック）

### 🚧 部分実装/未実装機能
- 🚧 リトライ機能（設定は定義済み、ロジック未実装）
- 🚧 `--full` オプション（定義済み、UseCase未対応）
- 🚧 raw JSON 保存（設定は定義済み、保存ロジック未実装）
- 🚧 Exit Code 3（ソース到達不可）の完全実装
- 🚧 robots.txt 尊重
- 🚧 User-Agent カスタマイズ
- 🚧 RSS 自動ビルドCI（参考実装のみ）

### 📝 設計上の注意点
- **並行実行**: `concurrency` は設定定義のみ、実装は逐次処理
- **ファイルロック**: StateManager で未実装（単一プロセス前提）
- **正規化**: コンテンツハッシュはそのまま計算（空白正規化なし）

## 受入基準（Acceptance Criteria）

### 実装済み（✅）
1. ✅ `bin/console tts:rss:build` を初回実行したら 指定ソースから 50 件以内の item を含む RSS 2.0 を `var/rss/tiktok-shop.xml` に出力できる
2. 同一内容で二度目の実行をしたら 変更がなければ `items_emitted=0` になり `lastBuildDate` だけが現在時刻に更新されない
3. ソース側で 1 ページ更新があったら 次回実行で `items_emitted>=1` が記録され RSS に新しい `<item>` が追加される `guid` は安定
4. ソースが 304 応答を返したとき HTTP 本文をダウンロードしない
5. ネットワーク一時障害 502 を 1 回返しても 再試行で復帰して成功する
6. `--dry-run` 実行時にファイルは書き換わらず サマリのみ表示される
7. `--output=-` で標準出力に RSS が出る
8. `--since` に未来時刻を渡したら Exit 2 でエラーメッセージを表示する
9. 解析不能なページが 1 件含まれても 全体は成功し 警告ログが出る
10. 生成された RSS は RSS Validator でエラーゼロ
11. 各 `<item>` の `description` は detail の値が使われ tree 由来でない
12. 各 `<item>` に `<content:encoded>` が含まれ detail の `content_html` が埋め込まれる
13. 初回実行後に `var/state/tiktok-shop.json` が作成され 内容に `version` `sources` が含まれる
14. 2 回目以降の実行で状態ファイルが更新され `etag` または `lastModified` が反映される
15. `items` の各要素は \`\`\*\* を ID として\*\* 一意に保存され 同じ `document_path` の二重登録は起きない（更新は上書き）
16. `--docs-dir=docs --output-basename=index.xml` で実行すると `docs/index.xml` が生成され GitHub に push 後 10 分以内に Pages から配信される
