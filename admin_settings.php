<?php
/** 발신(메일) 설정 뷰 — admin.php 안에서 include */
$token = csrf_token();
$fields = [
    'SMTP_HOST'      => ['SMTP 서버',                 'text'],
    'SMTP_PORT'      => ['포트 (465=SSL, 587=STARTTLS)', 'number'],
    'SMTP_USER'      => ['보내는 계정 (아이디/이메일)',  'text'],
    'SMTP_FROM'      => ['발신 주소 (From)',            'text'],
    'SMTP_FROM_NAME' => ['발신자 표시 이름',            'text'],
    'NOTIFY_TO'      => ['완료 알림 받는 주소 (운영진)', 'text'],
    'NOTIFY_TO_NAME' => ['받는 사람 이름',              'text'],
];
$dry = (bool)(int)mail_cfg('MAIL_DRY_RUN');
?>
<h2 class="section-title">⚙️ 발신(메일) 설정</h2>
<p class="hint">여기 값이 <code>config.php</code>보다 우선 적용됩니다. Gmail은 2단계 인증 후 <b>앱 비밀번호</b>를 발급해 넣으세요.
  일부 무료호스팅은 외부 SMTP가 막혀 발송이 실패할 수 있습니다.</p>

<form method="post" class="card-form">
  <input type="hidden" name="csrf" value="<?= h($token) ?>">
  <input type="hidden" name="action" value="settings_save">
  <div class="grid-form">
    <?php foreach ($fields as $k => [$lbl, $type]): ?>
      <label class="col-2"><?= h($lbl) ?>
        <input type="<?= $type ?>" name="<?= $k ?>" value="<?= h((string)mail_cfg($k)) ?>">
      </label>
    <?php endforeach; ?>
    <label class="col-2">비밀번호 (앱 비밀번호) — <span class="muted-cell">변경할 때만 입력, 비워두면 유지</span>
      <input type="password" name="SMTP_PASS" value="" placeholder="●●●● (변경 시에만)" autocomplete="new-password">
    </label>
    <label class="chk-inline col-2">
      <input type="checkbox" name="MAIL_DRY_RUN" value="1" <?= $dry ? 'checked' : '' ?>>
      실제 발송하지 않고 로그(mail_dryrun.log)만 남기기 — 테스트용
    </label>
  </div>
  <button class="btn-primary btn-sm" type="submit">설정 저장</button>
</form>

<form method="post" class="inline-form" onsubmit="return confirm('현재 설정으로 운영진 주소에 테스트 메일을 보낼까요?')">
  <input type="hidden" name="csrf" value="<?= h($token) ?>">
  <input type="hidden" name="action" value="settings_test">
  <button class="btn-sm" type="submit">✉ 테스트 메일 발송</button>
</form>
<p class="hint" style="margin-top:8px">현재 발신 계정: <b><?= h((string)mail_cfg('SMTP_USER') ?: '(미설정)') ?></b>
  · 받는 주소: <b><?= h((string)mail_cfg('NOTIFY_TO') ?: '(미설정)') ?></b>
  · 상태: <b><?= $dry ? '테스트(로그만)' : '실제 발송' ?></b></p>
