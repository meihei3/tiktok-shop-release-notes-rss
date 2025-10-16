## 🚀 基本使用法

```bash
composer install
composer generate-rss

# カスタム設定
php bin/console generate-rss --title="My Feed" --output="feed.xml"
```

## 🛠️ 開発コマンド

```bash
composer test          # テスト実行
composer phpcs         # コードスタイルチェック  
composer phpstan       # 静的解析（Level 8）
composer phpcbf        # スタイル自動修正
```

---

## 🤖 Claude Code開発ガイド

### 基本方針
- **外部データ優先**: 型安全性 < 堅牢性（外部API構造は不確実）
- **既存パターン踏襲**: `JsonContentFetcher`を参考に新機能実装
- **テスト必須**: 機能追加時は必ずユニットテスト作成

### 推奨アプローチ
✅ **DO**
- `readonly class`での新モデル作成
- PHPStan Level 8維持（`max`は外部APIデータと相性悪い）
- `$this->assert*()`スタイルのテスト
- コミット前の品質チェック実行
- **適切な粒度でコミット**: 機能単位で分割し、1コミット1機能を徹底

❌ **DON'T**
- PHPStan Level 9以上設定（外部データ処理困難）
- 外部APIの完全型定義（現実的でない）
- readonly classの変更
- 複数機能を1つのコミットにまとめる

### ファイル編集優先順位
1. **Core**: `src/Service/`, `src/Model/`
2. **Command**: `src/Command/GenerateRssCommand.php`
3. **Config**: `config/services.php`, `.env`
4. **Templates**: `templates/rss.xml.twig`
5. **Tests**: `tests/Unit/`

### 新機能追加手順
```bash
git checkout -b feature/new-fetcher
# 1. src/Service/NewContentFetcher.php 作成
# 2. tests/Unit/Service/NewContentFetcherTest.php 作成
# 3. config/services.php で登録
composer test && composer phpstan && composer phpcs
git commit -m "feat: NewContentFetcherの追加" && git push -u origin HEAD
```

---

## 🔧 技術仕様・制約

### コード品質
- **Modern PHP**: readonly classes, match expressions使用
- **PSR-12準拠**: コードスタイル必須
- **PHPStan Level 8**: 外部データ処理と型安全性のバランス最適

### 外部API対応
- データ構造不確実性への対応（デフォルト値、エラーハンドリング）
- ネットワークエラー・タイムアウト対策
- メモリ効率（大量データ処理）

### カスタマイズ方法
```php
// 1. ContentFetcher実装
readonly class MyFetcher implements ContentFetcherInterface {
    public function fetchContent(): array { /* 実装 */ }
}

// 2. DI登録（config/services.php）
$services->set(ContentFetcherInterface::class, MyFetcher::class);

// 3. 環境設定（.env）
CONTENT_SOURCE_URL=https://api.example.com/feed
```

---

## 🔄 開発ワークフロー

### ⚠️ 重要: 必ずブランチ作成
**mainブランチへの直接コミット・プッシュは絶対禁止**

### ブランチ・コミット
- **命名**: `feature/{{ticket-id}}`, `hotfix/{{ticket-id}}`, `docs/{{description}}`
- **作業前に必ずブランチ作成**: `git checkout -b feature/new-feature`
- **コミット粒度重視**: diff存在時は未完了でもコミット
- **適切な粒度**: 機能やレイヤーごとに分割（例: モデル→サービス→コマンド→テスト）
- **日本語メッセージ**: `feat: 理由` + 詳細リスト

### コミット粒度の例
```bash
# 良い例: 機能ごとに分割
git add composer.json .gitignore && git commit -m "build: プロジェクト基本設定"
git add src/Model/ && git commit -m "feat: ドメインモデルの追加"
git add src/Service/ConfigLoader.php && git commit -m "feat: 設定読み込み機能"
git add src/Service/DocumentFetcher.php && git commit -m "feat: APIフェッチャー実装"
git add tests/ && git commit -m "test: ユニットテスト追加"

# 悪い例: すべてを1つにまとめる
git add . && git commit -m "feat: すべての機能を実装"
```

### 正しい作業フロー
```bash
# 1. 必ずブランチ作成
git checkout -b feature/new-feature

# 2. 作業・コミット
git add . && git commit -m "feat: 新機能追加"

# 3. プッシュ・PR作成
git push -u origin HEAD
gh pr create --assignee @me --title "feat: 新機能追加"

# 4. PR承認後マージ
gh pr merge --squash
```

---

## 🆘 よくある問題・解決策

**PHPStanエラー**: `array<string, mixed>`型注釈、`??`演算子優先使用  
**外部API接続失敗**: JsonContentFetcherのエラーハンドリング参考  
**テストでAPI依存**: HttpClientInterfaceをモック化  
**CI失敗**: ローカルで全品質チェック通過確認後にプッシュ