<script>
window.__INPMNT__ = window.__INPMNT__ || {};
window.__INPMNT__.base = <?= json_encode(Http::front(), JSON_UNESCAPED_SLASHES) ?>;
window.__INPMNT__.path = function (p) {
  var base = window.__INPMNT__.base || "/index.php";
  if (p == null || p === "") {
    return base;
  }
  var hash = "", q = "", i;
  i = p.indexOf("#");
  if (i >= 0) {
    hash = p.slice(i);
    p = p.slice(0, i);
  }
  i = p.indexOf("?");
  if (i >= 0) {
    q = p.slice(i);
    p = p.slice(0, i);
  }
  if (p === "/" || p === "") {
    return base + q + hash;
  }
  if (p.charAt(0) !== "/") {
    p = "/" + p;
  }
  if (p.indexOf(base) === 0) {
    return p + q + hash;
  }
  return base + p + q + hash;
};
(function () {
  if (!("serviceWorker" in navigator)) {
    return;
  }
  navigator.serviceWorker.getRegistrations().then(function (regs) {
    regs.forEach(function (reg) { reg.unregister(); });
  });
  if (window.caches) {
    caches.keys().then(function (keys) {
      keys.forEach(function (key) { caches.delete(key); });
    });
  }
})();
</script>
