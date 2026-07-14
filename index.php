<?php
require_once __DIR__ . '/lib.php';

// 이미 로그인 상태면 앱으로
if (current_member()) { header('Location: app.php'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $m = login_by_phone($_POST['phone'] ?? '');
    if ($m) { header('Location: app.php'); exit; }
    $error = '등록되지 않은 번호입니다. 운영진에게 문의하세요.';
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= h(APP_TITLE) ?> — 로그인</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body class="login-body">
  <main class="login-card">
    <h1 class="login-title"><?= h(APP_TITLE) ?></h1>
    <p class="login-sub">참가자의 휴대폰 번호를 입력하세요.</p>

    <?php if ($error): ?>
      <div class="alert error" role="alert"><?= h($error) ?></div>
    <?php endif; ?>

    <form method="post" novalidate>
      <label for="phone">휴대폰번호</label>
      <input id="phone" name="phone" type="tel" inputmode="numeric"
             autocomplete="tel" placeholder="010-1234-5678"
             required autofocus aria-describedby="hint">
      <p id="hint" class="hint">참가자의 휴대폰 번호가 아니면 접속이 되지 않습니다.</p>
      <button type="submit" class="btn-primary">로그인</button>
    </form>

    <p class="admin-link"><a href="admin.php">관리자 로그인 →</a></p>
  </main>
</body>
</html>
