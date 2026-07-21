<?php
/** 관리자 비밀번호 변경 뷰 — admin.php 안에서 include */
$token = csrf_token();
$hasHash = (string)(setting('ADMIN_PASSWORD_HASH') ?? '') !== '';
?>
<h2 class="section-title">🔑 관리자 비밀번호 변경</h2>
<p class="hint">
  <?php if (!$hasHash): ?>
    아직 사용자 비밀번호가 지정되지 않아 <code>config.php</code> 의 <code>ADMIN_PASSWORD</code> 값이 사용 중입니다.
    아래에서 한 번 새로 지정하면, 그 뒤로는 여기서 저장한 비밀번호가 우선 적용됩니다 (config.php 값은 무시).
  <?php else: ?>
    현재 비밀번호를 확인한 뒤 새 비밀번호로 바꿉니다. 최소 8자 이상.
    바꾼 뒤에도 <code>config.php</code> 의 <code>ADMIN_PASSWORD</code> 값은 무시됩니다.
  <?php endif; ?>
</p>

<form method="post" class="card-form" autocomplete="off">
  <input type="hidden" name="csrf" value="<?= h($token) ?>">
  <input type="hidden" name="action" value="password_change">
  <div class="grid-form">
    <label class="col-2">현재 비밀번호
      <input type="password" name="current_password" required autocomplete="current-password" autofocus>
    </label>
    <label class="col-2">새 비밀번호 <span class="muted-cell">(최소 8자)</span>
      <input type="password" name="new_password" required minlength="8" autocomplete="new-password">
    </label>
    <label class="col-2">새 비밀번호 확인
      <input type="password" name="confirm_password" required minlength="8" autocomplete="new-password">
    </label>
  </div>
  <button class="btn-primary btn-sm" type="submit">비밀번호 변경</button>
</form>

<p class="hint" style="margin-top:12px">
  ⚠️ 변경 후에는 새 비밀번호로만 로그인할 수 있습니다. 잊지 않도록 안전한 곳에 보관해 주세요.
</p>
