<?php

namespace App\Services;

/**
 * データシート解析用プロンプトを正本 Markdown から読み出す。
 * ChatGPT 手動解析と Gemini 直結解析で指示内容を揃えるため、
 * プロンプトの重複定義を避ける。
 */
class DatasheetPromptService
{
    private const SOURCE_PATH = '../プロンプト/データシート解析プロンプト.md';

    private const COPY_MARKER = '## プロンプト本文（ここからコピー）';

    public function getPromptText(): string
    {
        $path = base_path(self::SOURCE_PATH);
        if (! is_file($path)) {
            return $this->fallbackPrompt();
        }

        $markdown = file_get_contents($path);
        if (! is_string($markdown) || trim($markdown) === '') {
            return $this->fallbackPrompt();
        }

        $markerPos = strpos($markdown, self::COPY_MARKER);
        if ($markerPos !== false) {
            $markdown = substr($markdown, $markerPos + strlen(self::COPY_MARKER));
        }

        $prompt = trim($markdown);

        return $prompt !== '' ? $prompt : $this->fallbackPrompt();
    }

    private function fallbackPrompt(): string
    {
        return <<<'PROMPT'
添付した電子部品のデータシート PDF を読み取り、以下の JSON フォーマットで部品情報を出力してください。

{
  "part_number": "型番",
  "manufacturer": "メーカー名",
  "common_name": "通称・シリーズ名",
  "component_types": ["部品種別候補"],
  "package_names": ["パッケージ候補"],
  "description": "部品の簡潔な説明",
  "specs": [
    {
      "name": "スペック名",
      "name_ja": "スペック項目の日本語名",
      "name_en": "スペック項目の英語名",
      "symbol": "略記号（例: h_FE, -V_CBO。- は通常、_ は下付き、~ は上付き）",
      "value_profile": "typ | range | max_only | min_only | triple",
      "value_typ": "typ値（なければ空文字）",
      "value_min": "最小値（なければ空文字）",
      "value_max": "最大値（なければ空文字）",
      "unit": "単位"
    }
  ]
}

JSON のみを出力し、説明文は付けないでください。
PROMPT;
    }
}
