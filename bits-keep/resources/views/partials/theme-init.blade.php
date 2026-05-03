<script>
// 目的: 初回描画前に保存済みテーマまたはOS設定を反映し、画面の白黒反転ちらつきを防ぐ。
// 入力: localStorage の bitskeep-theme と prefers-color-scheme。出力: html[data-theme]。
// 動作条件: ブラウザ環境で実行されること。副作用: documentElement の data-theme 属性を更新する。
(function(){
  var s = localStorage.getItem('bitskeep-theme');
  var t = (s === 'light' || s === 'dark') ? s
        : (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
  document.documentElement.setAttribute('data-theme', t);
})();
</script>
