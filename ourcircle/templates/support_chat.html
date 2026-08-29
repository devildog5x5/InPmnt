<div class="fsp-chat" id="fsp-chat">
  <button type="button" class="fsp-chat-toggle" id="fsp-chat-toggle" aria-expanded="false" aria-controls="fsp-chat-panel">Chat with us</button>
  <div class="fsp-chat-panel" id="fsp-chat-panel" hidden>
    <header class="fsp-chat-head">
      <strong>Family Shield Pro help</strong>
      <button type="button" class="fsp-chat-x" id="fsp-chat-close" aria-label="Close chat">&times;</button>
    </header>
    <div class="fsp-chat-log" id="fsp-chat-log"></div>
    <form class="fsp-chat-form" id="fsp-chat-form">
      <label class="sr-only" for="fsp-chat-input">Message</label>
      <input id="fsp-chat-input" maxlength="800" placeholder="Ask about plans, login, or the pause rule…" autocomplete="off" />
      <button class="btn" type="submit">Send</button>
    </form>
    <p class="fsp-chat-mail">Email <a href="mailto:CustomerService@FamilyShieldPro.com">CustomerService@FamilyShieldPro.com</a></p>
    <p class="fsp-chat-mail">This application offers guidance, not a guarantee.</p>
  </div>
</div>
<script>
(function () {
  var wrap = document.getElementById("fsp-chat");
  if (!wrap) return;
  var toggle = document.getElementById("fsp-chat-toggle");
  var panel = document.getElementById("fsp-chat-panel");
  var closeBtn = document.getElementById("fsp-chat-close");
  var log = document.getElementById("fsp-chat-log");
  var form = document.getElementById("fsp-chat-form");
  var input = document.getElementById("fsp-chat-input");
  var history = [];
  function add(role, text) {
    var el = document.createElement("div");
    el.className = "fsp-chat-msg " + role;
    el.textContent = text;
    log.appendChild(el);
    log.scrollTop = log.scrollHeight;
  }
  function open() {
    panel.hidden = false;
    toggle.setAttribute("aria-expanded", "true");
    wrap.classList.add("open");
    if (!log.childNodes.length) {
      add("assistant", "Hi — I can help with plans, login, and how OurCircle works. I will never tell you a request is safe. For a person, email CustomerService@FamilyShieldPro.com.");
    }
    input.focus();
  }
  function close() {
    panel.hidden = true;
    toggle.setAttribute("aria-expanded", "false");
    wrap.classList.remove("open");
  }
  toggle.addEventListener("click", function () {
    if (panel.hidden) open(); else close();
  });
  closeBtn.addEventListener("click", close);
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
</script>
