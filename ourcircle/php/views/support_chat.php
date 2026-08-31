<div class="fsp-chat" id="fsp-chat">
  <button type="button" class="fsp-chat-tab" id="fsp-chat-toggle" aria-expanded="false" aria-controls="fsp-chat-panel">Help</button>
  <div class="fsp-chat-panel" id="fsp-chat-panel" hidden>
    <header class="fsp-chat-head">
      <strong>Family Shield Pro help</strong>
      <button type="button" class="fsp-chat-hide" id="fsp-chat-close" aria-label="Hide help">Hide</button>
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
<script src="/static/js/fsp-chat.js?v=<?= Http::e(Product::version()) ?>"></script>
