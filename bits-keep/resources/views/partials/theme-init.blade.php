<script>
(function(){
  var s = localStorage.getItem('bitskeep-theme');
  var t = (s === 'light' || s === 'dark') ? s
        : (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
  document.documentElement.setAttribute('data-theme', t);
})();
</script>
