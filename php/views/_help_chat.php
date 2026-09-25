<?php
/** Copyright (c) 2026 Robert Foster */
$helpEmail = HelpChat::supportEmail();
?>
<div class="inp-chat" id="inp-chat" data-url="/support/chat" data-email="<?= Http::e($helpEmail) ?>">
  <button type="button" class="inp-chat-tab" id="inp-chat-toggle" aria-expanded="false" aria-controls="inp-chat-panel" aria-label="Need help?">
    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 16.5V7.5A3.5 3.5 0 0 1 8.5 4h7A3.5 3.5 0 0 1 19 7.5v5A3.5 3.5 0 0 1 15.5 16H9l-4 3.5z"/></svg>
  </button>
  <div class="inp-chat-panel" id="inp-chat-panel" hidden>
    <header class="inp-chat-head"><strong>Need help?</strong>
      <button type="button" class="inp-chat-hide" id="inp-chat-close" aria-label="Hide help">Hide</button>
    </header>
    <div class="inp-chat-log" id="inp-chat-log"></div>
    <form class="inp-chat-form" id="inp-chat-form">
      <label class="sr-only" for="inp-chat-input">Message</label>
      <input id="inp-chat-input" maxlength="800" placeholder="Ask about plans, invoices, or login." autocomplete="off" />
      <button class="btn" type="submit">Send</button>
    </form>
    <p class="inp-chat-mail">Email <a href="mailto:<?= Http::e($helpEmail) ?>"><?= Http::e($helpEmail) ?></a></p>
  </div>
</div>
<script src="/static/js/help-chat.js?v=<?= rawurlencode(Http::VERSION) ?>"></script>
<script src="/static/js/password-toggle.js?v=<?= rawurlencode(Http::VERSION) ?>"></script>
