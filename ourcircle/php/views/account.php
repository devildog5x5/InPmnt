<?php
/** @var array $flashes */
/** @var bool $totp_on */
/** @var array $recovery_codes */
View::appOpen(get_defined_vars());
$codes = is_array($recovery_codes ?? null) ? $recovery_codes : [];
?>
<h2>Password</h2>
<form method="post" action="/account/password">
  <label>Current password</label>
  <input name="current_password" type="password" required autocomplete="current-password" />
  <label>New password (8+)</label>
  <input name="password" type="password" required minlength="8" autocomplete="new-password" />
  <p><button class="btn" type="submit">Change password</button></p>
</form>
<h2>Two-factor authentication</h2>
<?php if ($codes): ?>
<div class="panel featured">
  <p><strong>Save these recovery codes now.</strong> Each works once if you lose the authenticator. We will not show them again.</p>
  <ul class="recovery-codes">
    <?php foreach ($codes as $c): ?>
    <li><?= Http::e((string) $c) ?></li>
    <?php endforeach; ?>
  </ul>
</div>
<?php endif; ?>
<?php if (!empty($totp_on)): ?>
<p>2FA is <strong>on</strong> for this login. Sign-in needs the authenticator (or a recovery code).</p>
<form method="post" action="/account/2fa/recovery" style="margin-bottom:16px">
  <label>Authenticator code to issue new recovery codes</label>
  <input class="otp" name="code" required inputmode="numeric" autocomplete="one-time-code" maxlength="8" />
  <p><button class="btn ghost" type="submit">Make new recovery codes</button></p>
</form>
<form method="post" action="/account/2fa/disable">
  <label>Authenticator code (or recovery code) to turn 2FA off</label>
  <input name="code" required autocomplete="one-time-code" />
  <p><button class="btn danger" type="submit">Turn off 2FA</button></p>
</form>
<?php else: ?>
<p>2FA is off. An authenticator app (Google Authenticator, Authy, 1Password, iCloud Keychain) adds a second step after the password.</p>
<p><a class="btn gold" href="/account/2fa/setup">Turn on 2FA</a></p>
<?php endif; ?>
<p class="disclaimer">Password reset: email link (needs SMTP/Resend in .env) or a recovery code on the forgot-password page.</p>
<?php View::appClose(); ?>
