<?php
/** @var string $secret_grouped */
/** @var string $otpauth */
View::appOpen(get_defined_vars());
?>
<p>Add this account in your authenticator app, then enter the 6-digit code to confirm.</p>
<p><strong>Setup key</strong></p>
<p class="otp-secret"><?= Http::e($secret_grouped ?? '') ?></p>
<p><a class="btn" href="<?= Http::e($otpauth ?? '') ?>">Add to authenticator app</a></p>
<p class="muted">On a phone, that link opens the app. On a computer, choose “enter a setup key” and paste the key above.</p>
<form method="post" action="/account/2fa/enable">
  <label>6-digit code</label>
  <input class="otp" name="code" required inputmode="numeric" autocomplete="one-time-code" maxlength="8" />
  <p><button class="btn gold wide" type="submit">Confirm and turn on 2FA</button></p>
</form>
<p><a href="/account">Cancel</a></p>
<?php View::appClose(); ?>
