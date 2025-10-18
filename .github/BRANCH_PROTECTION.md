# ブランチ保護設定ガイド

このプロジェクトでは、mainブランチへのマージ前にコード品質チェックを必須としています。

## GitHub ブランチ保護ルールの設定手順

1. リポジトリの **Settings** → **Branches** に移動
2. **Branch protection rules** セクションで **Add rule** をクリック
3. 以下の設定を行う:

### 基本設定
- **Branch name pattern**: `main`

### 必須チェック
- ✅ **Require status checks to pass before merging**
  - ✅ **Require branches to be up to date before merging**
  - 必須のステータスチェック:
    - `Code Quality Checks`

### 推奨設定
- ✅ **Require a pull request before merging**
  - Required approvals: 1 (個人プロジェクトの場合は0でも可)
- ✅ **Require conversation resolution before merging**
- ✅ **Do not allow bypassing the above settings**

## CIで実行されるチェック

**実行環境**: PHP 8.4

### 1. PHPUnit (テスト)
```bash
composer test
```
全てのユニットテストが通過する必要があります。

### 2. PHPStan (静的解析)
```bash
composer phpstan
```
PHPStan level max でエラーゼロが必要です。

### 3. PHP_CodeSniffer (コードスタイル)
```bash
composer phpcs
```
PSR-12準拠が必要です。

## ローカルでの事前チェック

PRを作成する前に、以下のコマンドで全チェックを実行してください:

```bash
composer test && composer phpstan && composer phpcs
```

エラーがある場合は以下で自動修正できます:

```bash
composer phpcbf
```
