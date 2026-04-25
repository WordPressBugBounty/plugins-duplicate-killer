/* global DK_COOKIE */
(function () {
  "use strict";

  if (!DK_COOKIE || typeof DK_COOKIE !== "object") return;

  var providers = DK_COOKIE.providers || {};
  var anySelector = DK_COOKIE.selector || "";
  var maxWait = parseInt(DK_COOKIE.max_wait_ms, 10);
  var interval = parseInt(DK_COOKIE.interval_ms, 10);

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

  function setCookie(name, days) {
    if (hasCookie(name)) return;

    var token = Date.now().toString(36) + Math.random().toString(36).slice(2);
    var d = new Date();
    d.setDate(d.getDate() + days);

    document.cookie =
      name + "=" + encodeURIComponent(token) +
      "; expires=" + d.toUTCString() +
      "; path=/; SameSite=Lax";
  }

  function safeId(str) {
    // Keep cookie names safe: [a-z0-9_-]
    return String(str || "")
      .toLowerCase()
      .replace(/[^a-z0-9_-]+/g, "_")
      .replace(/^_+|_+$/g, "");
  }

  function intOrNull(v) {
    var n = parseInt(v, 10);
    return isFinite(n) ? n : null;
  }

  function extractFirstNumber(s) {
    var m = String(s || "").match(/(\d+)/);
    return m ? intOrNull(m[1]) : null;
  }

  // Provider-specific form identifiers (best effort, works broadly)
  function getFormIdentifier(providerKey, formEl) {
    if (!formEl) return null;

    if (providerKey === "cf7") {
      var cf7 = formEl.querySelector('input[name="_wpcf7"]');
      return cf7 ? intOrNull(cf7.value) : null;
    }

    if (providerKey === "wpforms") {
      // <form id="wpforms-form-96"> OR hidden input name="wpforms[id]"
      var idAttr = formEl.getAttribute("id");
      var n = extractFirstNumber(idAttr);
      if (n) return n;

      var hid = formEl.querySelector('input[name="wpforms[id]"]');
      return hid ? intOrNull(hid.value) : null;
    }

    if (providerKey === "forminator") {
      // <form id="forminator-module-123">
      var fid = formEl.getAttribute("id");
      var fn = extractFirstNumber(fid);
      return fn ? fn : null;
    }

    if (providerKey === "ninja_forms") {
	  // In many Ninja Forms setups, there is no data-nf-form-id and the <form> has no id.
	  // The stable identifier is usually on the container: #nf-form-{ID}-cont
	  // Example: <div id="nf-form-1-cont" class="nf-form-cont">
	  var cont = null;

	  if (formEl.closest) {
		cont = formEl.closest(".nf-form-cont");
	  }

	  if (cont) {
		var cid = cont.getAttribute("id");
		var n = extractFirstNumber(cid); // "nf-form-1-cont" -> 1
		if (n) return n;
	  }

	  // Fallback: try data-nf-form-id on closest wrapper if present
	  if (formEl.closest) {
		var wrap = formEl.closest("[data-nf-form-id]");
		if (wrap) {
		  var wdata = wrap.getAttribute("data-nf-form-id");
		  var wdn = intOrNull(wdata);
		  if (wdn) return wdn;
		}
	  }

	  // Last fallback: parse numeric from any parent id
	  var pid = formEl.getAttribute("id");
	  return pid ? (extractFirstNumber(pid) || null) : null;
	}

    if (providerKey === "breakdance") {
	  var hiddenFormId = formEl.querySelector('input[name="form_id"]');
	  var hiddenId = hiddenFormId ? intOrNull(hiddenFormId.value) : null;
	  if (hiddenId) return hiddenId;

	  var bd = formEl.getAttribute("data-bde-form-id");
	  var bn = intOrNull(bd);
	  if (bn) return bn;

	  var bid = formEl.getAttribute("id");
	  var bnn = extractFirstNumber(bid);
	  return bnn ? bnn : null;
	}

    if (providerKey === "elementor_forms") {
      // Elementor Forms often have hidden input "form_id"
      var ef = formEl.querySelector('input[name="form_id"]');
      if (ef && ef.value) return safeId(ef.value);

      // fallback: data-id or id attribute
      var did = formEl.getAttribute("data-id") || formEl.getAttribute("id");
      return did ? safeId(did) : null;
    }

    if (providerKey === "formidable") {
	  // Prefer hidden form_id if present (string like "contact-us.2")
	  var fh = formEl.querySelector('input[name="form_id"]');
	  if (fh && fh.value) return safeId(fh.value);

	  // Fallback: id attribute
	  var frmid = formEl.getAttribute("id");
	  return frmid ? safeId(frmid) : null;
	}

    // Generic fallback: numeric from id, else safe id string
    var generic = formEl.getAttribute("id");
    return extractFirstNumber(generic) || (generic ? safeId(generic) : null);
  }

  function hasAnySupportedForm() {
    if (!anySelector) return false;
    try {
      return !!document.querySelector(anySelector);
    } catch (e) {
      return false;
    }
  }

  function setCookiesForDetectedForms() {
    var didSomething = false;

    for (var key in providers) {
      if (!Object.prototype.hasOwnProperty.call(providers, key)) continue;

      var p = providers[key];
      if (!p || !p.selector) continue;

      var nodes;
      try {
        nodes = document.querySelectorAll(p.selector);
      } catch (e) {
        continue;
      }

      if (!nodes || !nodes.length) continue;

      for (var i = 0; i < nodes.length; i++) {
		  var el = nodes[i];

		  var formEl = el.tagName && el.tagName.toLowerCase() === "form"
			? el
			: (el.querySelector ? el.querySelector("form") : null);

		  if (!formEl) continue;

		  var id = getFormIdentifier(key, formEl);
		  if (id === null || id === undefined || id === "") continue;

		  var map = p.per_form_days || {};
		  var idKey = String(id);

		  // STRICT PRO: set cookie only if ID exists in allowlist map
			var cookieSuffixKey = idKey; // by default, cookie suffix is the detected ID

			// STRICT PRO: set cookie only if ID exists in allowlist map
			if (!Object.prototype.hasOwnProperty.call(map, idKey)) {

			  // Elementor GROUP fallback:
			  // - if map has "group_<safe_form_name>" use it
			  // - else if map has generic "group" use its days, but cookie name must be per form-name group
			  if (key === "elementor_forms") {
				  var n = formEl.getAttribute("name") || formEl.getAttribute("aria-label") || "";
				  n = String(n).replace(/\s+/g, " ").trim();

				  var groupKey = "group_" + safeId(n);

				  if (groupKey && Object.prototype.hasOwnProperty.call(map, groupKey)) {
					idKey = groupKey;
					cookieSuffixKey = groupKey;
				  } else {
					continue;
				  }
				} else {
				  continue;
				}
			}

			var days = parseInt(map[idKey], 10);
			if (!days || days < 1) days = 1;

			// Cookie name uses suffixKey (so generic "group" still creates per-form-name cookie)
			var safeSuffix = safeId(cookieSuffixKey);
			var cookieName = (p.cookie_prefix || ("dk_form_cookie_" + key + "_")) + safeSuffix;

			setCookie(cookieName, days);
			didSomething = true;		  var days = parseInt(map[idKey], 10);

		}
    }

    return didSomething;
  }

  function run() {
    if (!hasAnySupportedForm()) return false;
    return setCookiesForDetectedForms();
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
