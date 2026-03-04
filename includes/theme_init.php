<script>
  (function() {
    var c = document.cookie.match(/mp-theme=([^;]+)/);
    var t = c ? decodeURIComponent(c[1]) : (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
    document.documentElement.setAttribute('data-bs-theme', t);
  })();
</script>