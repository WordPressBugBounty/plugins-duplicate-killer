/* global DK_COOKIE */
(function () {
  "use strict";

  if (!DK_COOKIE || typeof DK_COOKIE !== "object") return;

  var cookieName = DK_COOKIE.cookie_name || "dk_form_cookie";
  var days = parseInt(DK_COOKIE.days, 10);
  var selector = DK_COOKIE.selector || "";
  var maxWait = parseInt(DK_COOKIE.max_wait_ms, 10);
  var interval = parseInt(DK_COOKIE.interval_ms, 10);

  if (!days || days < 1) days = 7;
  if (!maxWait || maxWait < 0) maxWait = 3000;
  if (!interval || interval < 20) interval = 100;

  function hasCookie(name) {
    var parts = document.cookie.split(";");
    for (var i = 0; i < parts.length; i++) {
      var c = parts[i].trim();
      if (c.indexOf(name + "=") === 0) return true;
    }
    return false;
  }

  function setCookieOnce() {
    if (hasCookie(cookieName)) return;

    // Client-side token is sufficient for "unique per browser" logic
    var token = Date.now().toString(36) + Math.random().toString(36).slice(2);

    var d = new Date();
    d.setDate(d.getDate() + days);

    document.cookie =
      cookieName + "=" + encodeURIComponent(token) +
      "; expires=" + d.toUTCString() +
      "; path=/; SameSite=Lax";
  }

  function hasAnySupportedForm() {
    if (!selector) return false;
    try {
      return !!document.querySelector(selector);
    } catch (e) {
      return false;
    }
  }

  function run() {
    if (hasAnySupportedForm()) {
      setCookieOnce();
      return true;
    }
    return false;
  }

  // Try now + short retry window (covers late renders/builders)
  var start = Date.now();
  var t = setInterval(function () {
    if (run() || (Date.now() - start) >= maxWait) {
      clearInterval(t);
    }
  }, interval);

  document.addEventListener("DOMContentLoaded", function () {
    run();
  });
})();