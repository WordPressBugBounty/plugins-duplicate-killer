(function () {
  "use strict";

  window.dkCopySupportInfo = function () {
    const el = document.getElementById("dk-support-info");
    const text = el ? (el.value || el.textContent || "") : "";

    const ok = () => {
      const s = document.getElementById("dk-copy-status");
      if (s) {
        s.style.display = "block";
        setTimeout(() => (s.style.display = "none"), 2000);
      }
    };

    function fallback() {
      const ta = document.createElement("textarea");
      ta.value = text;
      ta.setAttribute("readonly", "");
      ta.style.position = "fixed";
      ta.style.opacity = "0";
      document.body.appendChild(ta);
      ta.select();
      try {
        document.execCommand("copy");
      } catch (e) {}
      document.body.removeChild(ta);
      ok();
    }

    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard.writeText(text).then(ok).catch(fallback);
    } else {
      fallback();
    }
  };
})();
