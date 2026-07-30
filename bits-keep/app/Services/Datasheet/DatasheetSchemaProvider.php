<?php

namespace App\Services\Datasheet;

/**
 * 解析結果の JSON スキーマを提供する。
 * 応答本文から JSON を探索して取り出す処理を持たないために、
 * 解析エンジン側へ「最終応答はこの形にせよ」と渡すためのスキーマ定義を1箇所へ置く。
 * 項目の意味と抽出ルールの正本は `プロンプト/データシート解析プロンプト.md` であり、
 * ここはその形だけを機械可読な形で表す。
 */
class DatasheetSchemaProvider
{
    /**
     * 目的: 解析結果の JSON スキーマを返す。
     * 機能: プロンプト正本の出力フォーマットに対応するスキーマを組み立てる。
     * 入力: なし。
     * 出力: JSON Schema を表す配列。
     * 動作条件: なし。
     * 副作用: なし。
     *
     * @return array<string, mixed>
     */
    public function schema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            // 未取得項目を省略ではなく空値で返させ、後段の欠落判定を単純にする
            'required' => [
                'part_number',
                'manufacturer',
                'common_name',
                'component_types',
                'package_names',
                'description',
                'specs',
            ],
            'properties' => [
                'part_number' => [
                    'type' => 'string',
                    'description' => 'データシート表紙またはタイトルの型番。複数ある場合はシリーズ代表を1つ。不明なら空文字。',
                ],
                'manufacturer' => [
                    'type' => 'string',
                    'description' => 'データシートに記載されたメーカー正式名称。不明なら空文字。',
                ],
                'common_name' => [
                    'type' => 'string',
                    'description' => '通称・機能名・シリーズ名。不明なら空文字。',
                ],
                'component_types' => [
                    'type' => 'array',
                    'description' => '部品種別・部品分類の候補。代表的なものから最大3件。不明なら空配列。',
                    'items' => ['type' => 'string'],
                ],
                'package_names' => [
                    'type' => 'array',
                    'description' => 'パッケージ候補。不明なら空配列。',
                    'items' => ['type' => 'string'],
                ],
                'description' => [
                    'type' => 'string',
                    'description' => '部品の簡潔な説明。1〜2文。日本語可。',
                ],
                'specs' => [
                    'type' => 'array',
                    'description' => 'データシートから読み取ったスペック詳細。',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => [
                            'name',
                            'name_ja',
                            'name_en',
                            'symbol',
                            'value_profile',
                            'value_typ',
                            'value_min',
                            'value_max',
                            'unit',
                        ],
                        'properties' => [
                            'name' => [
                                'type' => 'string',
                                'description' => 'データシート上の表記そのままのスペック名。',
                            ],
                            'name_ja' => [
                                'type' => 'string',
                                'description' => 'スペック詳細の日本語名。',
                            ],
                            'name_en' => [
                                'type' => 'string',
                                'description' => 'スペック詳細の英語名。',
                            ],
                            'symbol' => [
                                'type' => 'string',
                                'description' => '略記号。下付きは _ 、上付きは ~ で表す（例: h_FE, V_CBO）。',
                            ],
                            'value_profile' => [
                                'type' => 'string',
                                'description' => '値の持ち方。typ は代表値のみ、range は最小と最大、max_only は最大のみ、min_only は最小のみ、triple は最小と代表と最大。',
                                'enum' => ['typ', 'range', 'max_only', 'min_only', 'triple'],
                            ],
                            'value_typ' => [
                                'type' => 'string',
                                'description' => '代表値。該当しないなら空文字。単位は含めない。',
                            ],
                            'value_min' => [
                                'type' => 'string',
                                'description' => '最小値。該当しないなら空文字。単位は含めない。',
                            ],
                            'value_max' => [
                                'type' => 'string',
                                'description' => '最大値。該当しないなら空文字。単位は含めない。',
                            ],
                            'unit' => [
                                'type' => 'string',
                                'description' => '単位。無単位なら空文字。',
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * 目的: スキーマを JSON 文字列として返す。
     * 機能: schema() の配列を、日本語の説明文を壊さない形で JSON へ整形する。
     * 入力: なし。
     * 出力: JSON 文字列。
     * 動作条件: なし。
     * 副作用: なし。
     */
    public function toJson(): string
    {
        return (string) json_encode(
            $this->schema(),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
        );
    }
}
