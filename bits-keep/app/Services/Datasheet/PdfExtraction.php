<?php

namespace App\Services\Datasheet;

/**
 * PDF から取り出した解析入力。
 * テキストとして渡す場合とページ画像として渡す場合で解析エンジンへの渡し方が変わるため、
 * どちらの形で取り出せたかを型として持たせる。
 */
class PdfExtraction
{
    // テキスト層から文字として取り出した
    public const MODE_TEXT = 'text';

    // テキスト層が無く、ページ画像として取り出した
    public const MODE_IMAGE = 'image';

    /**
     * 目的: 取り出した入力の内容を保持する。
     * 機能: 取り出し方式、テキストファイルパス、画像パス配列、規模を保持する。
     * 入力: $mode は MODE_*、$textPath はテキストファイルの絶対パス、$imagePaths は画像の絶対パス配列、$textLength はテキスト文字数。
     * 出力: インスタンス。
     * 動作条件: $mode に応じて $textPath または $imagePaths が埋まっていること。
     * 副作用: なし。
     *
     * @param  array<int, string>  $imagePaths
     */
    private function __construct(
        public readonly string $mode,
        public readonly ?string $textPath,
        public readonly array $imagePaths,
        public readonly int $textLength,
    ) {}

    /**
     * 目的: テキストとして取り出した結果を作る。
     * 機能: 方式をテキストに固定してインスタンスを生成する。
     * 入力: $textPath はテキストファイルの絶対パス、$textLength は文字数。
     * 出力: インスタンス。
     * 動作条件: $textPath が存在すること。
     * 副作用: なし。
     */
    public static function text(string $textPath, int $textLength): self
    {
        return new self(self::MODE_TEXT, $textPath, [], $textLength);
    }

    /**
     * 目的: ページ画像として取り出した結果を作る。
     * 機能: 方式を画像に固定してインスタンスを生成する。
     * 入力: $imagePaths はページ順に並んだ画像の絶対パス配列。
     * 出力: インスタンス。
     * 動作条件: $imagePaths が1件以上あること。
     * 副作用: なし。
     *
     * @param  array<int, string>  $imagePaths
     */
    public static function images(array $imagePaths): self
    {
        return new self(self::MODE_IMAGE, null, $imagePaths, 0);
    }

    /**
     * 目的: 解析へ渡したページ数を返す。
     * 機能: 画像方式は画像枚数、テキスト方式はページ概念を持たないため null を返す。
     * 入力: なし。
     * 出力: ページ数、またはテキスト方式なら null。
     * 動作条件: なし。
     * 副作用: なし。
     */
    public function pageCount(): ?int
    {
        // テキスト方式はページ単位で切っていないため、ページ数として記録できる値を持たない
        return $this->mode === self::MODE_IMAGE ? count($this->imagePaths) : null;
    }
}
