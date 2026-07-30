<?php

namespace App\Services\Datasheet;

/**
 * 解析1件の成果。
 * 正規化済み結果に加えて、PDFをどう渡したかを併せて持つ。
 * 所要時間や精度を後から調べるとき、テキストで渡したのか画像で渡したのかが分からないと
 * 原因の切り分けができないため、記録対象として扱う。
 */
class DatasheetAnalysisOutcome
{
    /**
     * 目的: 解析成果を保持する。
     * 機能: 正規化済み結果、入力方式、ページ数を保持する。
     * 入力: $result は正規化済み解析結果、$inputMode は PdfExtraction の MODE_* または null、$inputPageCount は渡したページ数または null。
     * 出力: インスタンス。
     * 動作条件: $result が正規化済みであること。
     * 副作用: なし。
     *
     * @param  array<string, mixed>  $result
     */
    public function __construct(
        public readonly array $result,
        public readonly ?string $inputMode = null,
        public readonly ?int $inputPageCount = null,
    ) {}
}
