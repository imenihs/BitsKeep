<!-- resources/views/components/index.blade.php<!DOCTYPE html> -->
<html lang="ja">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>部品一覧</title>
  @vite([
    'resources/css/theme.css',
    'resources/css/components/component-list.css',
    'resources/js/components/component-list.js'
  ])
</head>
<body class="min-h-screen">
  <div id="app" class="text-[var(--color-text)] bg-[var(--color-bg)]"></div>
</body>
</html>
