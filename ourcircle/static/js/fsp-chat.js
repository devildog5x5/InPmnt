(function () {
  var wrap = document.getElementById("fsp-chat");
  if (!wrap) return;
  var toggle = wrap.querySelector("#fsp-chat-toggle");
  var panel = wrap.querySelector("#fsp-chat-panel");
  var closeBtn = wrap.querySelector("#fsp-chat-close");
  var log = wrap.querySelector("#fsp-chat-log");
  var form = wrap.querySelector("#fsp-chat-form");
  var input = wrap.querySelector("#fsp-chat-input");
  if (!toggle || !panel || !closeBtn || !log || !form || !input) return;
  var history = [];
  var ignoreToggleUntil = 0;

  function add(role, text) {
    var el = document.createElement("div");
    el.className = "fsp-chat-msg " + role;
    el.textContent = text;
    log.appendChild(el);
    log.scrollTop = log.scrollHeight;
  }

  function openBox() {
    wrap.classList.add("open");
    panel.hidden = false;
    panel.style.display = "flex";
    toggle.setAttribute("aria-expanded", "true");
    try { localStorage.setItem("fsp-help", "open"); } catch (e) {}
    if (!log.childNodes.length) {
      add("assistant", "Hi — I can help with plans, login, and how OurCircle works. I will never tell you a request is safe. For a person, email CustomerService@FamilyShieldPro.com.");
    }
    input.focus();
  }

  function hideBox(ev) {
    if (ev) {
      ev.preventDefault();
      ev.stopPropagation();
    }
    wrap.classList.remove("open");
    panel.hidden = true;
    panel.style.display = "none";
    toggle.setAttribute("aria-expanded", "false");
    try { localStorage.setItem("fsp-help", "hidden"); } catch (e) {}
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
    if (localStorage.getItem("fsp-help") === "open") openBox();
  } catch (e) {}
  form.addEventListener("submit", function (ev) {
    ev.preventDefault();
    var msg = (input.value || "").trim();
    if (!msg) return;
    input.value = "";
    add("user", msg);
    history.push({ role: "user", content: msg });
    var wait = document.createElement("div");
    wait.className = "fsp-chat-msg assistant pending";
    wait.textContent = "…";
    log.appendChild(wait);
    fetch("/support/chat", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ message: msg, history: history.slice(-8) })
    }).then(function (r) { return r.json(); }).then(function (data) {
      wait.remove();
      var reply = (data && data.reply) ? data.reply : "Please email CustomerService@FamilyShieldPro.com.";
      add("assistant", reply);
      history.push({ role: "assistant", content: reply });
    }).catch(function () {
      wait.remove();
      add("assistant", "The chat could not reach the server. Email CustomerService@FamilyShieldPro.com.");
    });
  });
})();
