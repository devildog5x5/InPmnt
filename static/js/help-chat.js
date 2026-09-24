(function () {
  var wrap = document.getElementById("inp-chat");
  if (!wrap) return;
  var toggle = wrap.querySelector("#inp-chat-toggle");
  var panel = wrap.querySelector("#inp-chat-panel");
  var closeBtn = wrap.querySelector("#inp-chat-close");
  var log = wrap.querySelector("#inp-chat-log");
  var form = wrap.querySelector("#inp-chat-form");
  var input = wrap.querySelector("#inp-chat-input");
  if (!toggle || !panel || !closeBtn || !log || !form || !input) return;
  var history = [];
  var ignoreToggleUntil = 0;
  var chatUrl = wrap.getAttribute("data-url") || "/support/chat";
  var supportEmail = wrap.getAttribute("data-email") || "support@invcpay.com";

  function linkify(text) {
    var div = document.createElement("div");
    div.textContent = text == null ? "" : String(text);
    var html = div.innerHTML;
    html = html.replace(/(https?:\/\/[^\s<]+)/g, '<a href="$1">$1</a>');
    html = html.replace(
      /(^|[\s>])([A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,})/g,
      '$1<a href="mailto:$2">$2</a>'
    );
    return html;
  }

  function add(role, text) {
    var el = document.createElement("div");
    el.className = "inp-chat-msg " + role;
    if (role === "assistant") el.innerHTML = linkify(text);
    else el.textContent = text;
    log.appendChild(el);
    log.scrollTop = log.scrollHeight;
  }

  function csrf() {
    var meta = document.querySelector('meta[name="csrf-token"]');
    if (meta && meta.content) return meta.content;
    var inp = document.querySelector('input[name="csrf"]');
    return inp ? inp.value : "";
  }

  function openBox() {
    wrap.classList.add("open");
    panel.hidden = false;
    panel.style.display = "flex";
    toggle.setAttribute("aria-expanded", "true");
    try { localStorage.setItem("inp-help", "open"); } catch (e) {}
    if (!log.childNodes.length) {
      add("assistant", "Hi — I can help with plans, invoices, reminders, and login. For a person, email " + supportEmail + ".");
    }
    input.focus();
  }

  function hideBox(ev) {
    if (ev) { ev.preventDefault(); ev.stopPropagation(); }
    wrap.classList.remove("open");
    panel.hidden = true;
    panel.style.display = "none";
    toggle.setAttribute("aria-expanded", "false");
    try { localStorage.setItem("inp-help", "hidden"); } catch (e) {}
    ignoreToggleUntil = Date.now() + 500;
  }

  toggle.addEventListener("click", function (ev) {
    if (Date.now() < ignoreToggleUntil) {
      ev.preventDefault();
      ev.stopPropagation();
      return;
    }
    if (wrap.classList.contains("open")) hideBox(ev);
    else openBox();
  });
  closeBtn.addEventListener("click", hideBox);
  closeBtn.addEventListener("pointerdown", hideBox);
  try {
    if (localStorage.getItem("inp-help") === "open") openBox();
  } catch (e) {}

  form.addEventListener("submit", function (ev) {
    ev.preventDefault();
    var msg = (input.value || "").trim();
    if (!msg) return;
    input.value = "";
    add("user", msg);
    history.push({ role: "user", content: msg });
    var wait = document.createElement("div");
    wait.className = "inp-chat-msg assistant pending";
    wait.textContent = "…";
    log.appendChild(wait);
    fetch(chatUrl, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-CSRF-Token": csrf()
      },
      body: JSON.stringify({ message: msg, history: history.slice(-8) })
    }).then(function (r) { return r.json(); }).then(function (data) {
      wait.remove();
      var reply = (data && data.reply) ? data.reply : ("Please email " + supportEmail + ".");
      add("assistant", reply);
      history.push({ role: "assistant", content: reply });
    }).catch(function () {
      wait.remove();
      add("assistant", "The chat could not reach the server. Email " + supportEmail + ".");
    });
  });
})();
