<?php
require_once __DIR__ . '/lib.php';

// ── 로그아웃 ──────────────────────────────────────────────
if (isset($_GET['logout'])) { unset($_SESSION['is_admin']); header('Location: admin.php'); exit; }

// ── 로그인 처리 ──────────────────────────────────────────
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !is_admin() && isset($_POST['password'])) {
    if (hash_equals(ADMIN_PASSWORD, (string)($_POST['password'] ?? ''))) {
        session_regenerate_id(true);
        $_SESSION['is_admin'] = true;
        header('Location: admin.php'); exit;
    }
    $error = '비밀번호가 올바르지 않습니다.';
}

// ── 로그인 화면 ──────────────────────────────────────────
if (!is_admin()):
?>
<!DOCTYPE html>
<html lang="ko"><head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h(APP_TITLE) ?> — 관리자</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body class="login-body">
  <main class="login-card">
    <h1 class="login-title">관리자 로그인</h1>
    <?php if ($error): ?><div class="alert error" role="alert"><?= h($error) ?></div><?php endif; ?>
    <form method="post">
      <label for="pw">비밀번호</label>
      <input id="pw" name="password" type="password" required autofocus>
      <button type="submit" class="btn-primary">로그인</button>
    </form>
    <p class="admin-link"><a href="index.php">← 참가자 로그인</a></p>
  </main>
</body></html>
<?php
    exit;
endif;

// ══════════════════════════════════════════════════════════
//  여기부터 관리자 인증됨
// ══════════════════════════════════════════════════════════

/** PRG 패턴용 플래시 메시지 저장 */
function set_flash(string $type, string $msg): void {
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}

/** 업로드된 CSV 파일을 파싱해 참가자 일괄 등록 */
function admin_handle_csv_import(): array {
    if (empty($_FILES['csv']) || ($_FILES['csv']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('CSV 파일 업로드에 실패했습니다.');
    }
    $data = file_get_contents($_FILES['csv']['tmp_name']);
    if ($data === false) throw new RuntimeException('파일을 읽을 수 없습니다.');
    $data = preg_replace('/^\xEF\xBB\xBF/', '', $data); // BOM 제거
    $rows = [];
    foreach (preg_split('/\r\n|\r|\n/', $data) as $idx => $line) {
        if (trim($line) === '') continue;
        // 첫 줄이 헤더면 스킵
        if ($idx === 0 && (mb_stripos($line, '휴대폰') !== false || mb_stripos($line, '이름') !== false)) continue;
        $rows[] = str_getcsv($line);
    }
    return import_members_csv($rows);
}

/** 제출 현황/응답 상세를 CSV 로 스트리밍 (엑셀 한글용 UTF-8 BOM) */
function admin_export_csv(string $type): void {
    $type = $type === 'detail' ? 'detail' : 'summary';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="monitoring_' . $type . '.csv"');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');

    if ($type === 'detail') {
        // 응답 상세: 참가자별 × 항목별
        fputcsv($out, ['조', '이름', '탭', '섹션', '항목', '응답', '개선사항']);
        $rows = db()->query(
            'SELECT t.name AS team, m.name AS member, s.name AS tab, i.section, i.label,
                    r.value, r.note
             FROM members m
             JOIN teams t ON t.id = m.team_id
             CROSS JOIN items i
             JOIN spots s ON s.id = i.spot_id
             LEFT JOIN responses r ON r.item_id = i.id AND r.member_id = m.id
             ORDER BY t.id, m.id, s.sort_order, i.sort_order, i.id')->fetchAll();
        foreach ($rows as $r) {
            fputcsv($out, [$r['team'], $r['member'], $r['tab'], $r['section'], $r['label'],
                           $r['value'], $r['note']]);
        }
    } else {
        // 제출 요약: 참가자별 제출 여부
        $req = required_item_count();
        fputcsv($out, ['조', '이름', '휴대폰', '제출', '제출시각', '조사원구분', '관광지명', '응답필수', '필수총']);
        $rows = db()->query(
            'SELECT t.name AS team, m.name AS member, m.phone, m.id AS mid,
                    sub.submitted_at, sub.surveyor_type, sub.site_name
             FROM members m JOIN teams t ON t.id = m.team_id
             LEFT JOIN submissions sub ON sub.member_id = m.id
             ORDER BY t.id, m.id')->fetchAll();
        foreach ($rows as $r) {
            $done = member_answered_count((int)$r['mid']);
            fputcsv($out, [$r['team'], $r['member'], $r['phone'],
                           $r['submitted_at'] ? '제출' : '미제출',
                           $r['submitted_at'] ? substr($r['submitted_at'], 0, 16) : '',
                           $r['surveyor_type'], $r['site_name'], $done, $req]);
        }
    }
    fclose($out);
}

// ── CSV 내보내기 (HTML 출력 전에 처리) ────────────────────
if (($_GET['action'] ?? '') === 'export') {
    admin_export_csv((string)($_GET['type'] ?? 'summary'));
    exit;
}

// ── POST 액션 디스패치 (CSRF 검증 → 처리 → PRG 리다이렉트) ─
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $redirect = 'admin.php';
    try {
        if (!csrf_verify($_POST['csrf'] ?? null)) {
            throw new RuntimeException('보안 토큰이 만료되었습니다. 다시 시도하세요.');
        }
        switch ($_POST['action']) {
            // 참가자
            case 'member_add':
                create_member((int)($_POST['team_id'] ?? 0), $_POST['name'] ?? '', $_POST['phone'] ?? '');
                set_flash('ok', '참가자를 등록했습니다.');
                $redirect = 'admin.php?view=members'; break;
            case 'member_update':
                update_member((int)($_POST['id'] ?? 0), (int)($_POST['team_id'] ?? 0), $_POST['name'] ?? '', $_POST['phone'] ?? '');
                set_flash('ok', '참가자 정보를 수정했습니다.');
                $redirect = 'admin.php?view=members'; break;
            case 'member_delete':
                delete_member((int)($_POST['id'] ?? 0));
                set_flash('ok', '참가자를 삭제했습니다.');
                $redirect = 'admin.php?view=members'; break;
            case 'members_import':
                $rep = admin_handle_csv_import();
                $msg = "일괄 등록 완료 — 추가 {$rep['added']}건, 중복 {$rep['dup']}건, 오류 {$rep['error']}건";
                if ($rep['errors']) $msg .= ' · ' . implode(' / ', array_slice($rep['errors'], 0, 5));
                set_flash($rep['error'] ? 'err' : 'ok', $msg);
                $redirect = 'admin.php?view=members'; break;

            // 조
            case 'team_add':
                create_team($_POST['name'] ?? '');
                set_flash('ok', '조를 추가했습니다.');
                $redirect = 'admin.php?view=members'; break;
            case 'team_rename':
                rename_team((int)($_POST['id'] ?? 0), $_POST['name'] ?? '');
                set_flash('ok', '조 이름을 변경했습니다.');
                $redirect = 'admin.php?view=members'; break;
            case 'team_delete':
                delete_team((int)($_POST['id'] ?? 0));
                set_flash('ok', '조를 삭제했습니다. (소속 참가자·체크 포함)');
                $redirect = 'admin.php?view=members'; break;

            // 관광지
            case 'spot_add':
                create_spot($_POST['name'] ?? '', (int)($_POST['sort_order'] ?? 0));
                set_flash('ok', '관광지를 추가했습니다.');
                $redirect = 'admin.php?view=checklist'; break;
            case 'spot_update':
                update_spot((int)($_POST['id'] ?? 0), $_POST['name'] ?? '', (int)($_POST['sort_order'] ?? 0));
                set_flash('ok', '관광지를 수정했습니다.');
                $redirect = 'admin.php?view=checklist'; break;
            case 'spot_delete':
                delete_spot((int)($_POST['id'] ?? 0));
                set_flash('ok', '관광지를 삭제했습니다. (소속 항목 포함)');
                $redirect = 'admin.php?view=checklist'; break;

            // 항목
            case 'item_add':
                create_item((int)($_POST['spot_id'] ?? 0), $_POST['label'] ?? '',
                            $_POST['type'] ?? 'check', $_POST['options'] ?? null,
                            !empty($_POST['required']), !empty($_POST['has_note']),
                            $_POST['section'] ?? null, $_POST['hint'] ?? null,
                            (int)($_POST['sort_order'] ?? 0));
                set_flash('ok', '항목을 추가했습니다.');
                $redirect = 'admin.php?view=checklist&spot=' . (int)($_POST['spot_id'] ?? 0); break;
            case 'item_update':
                update_item((int)($_POST['id'] ?? 0), $_POST['label'] ?? '',
                            $_POST['type'] ?? 'check', $_POST['options'] ?? null,
                            !empty($_POST['required']), !empty($_POST['has_note']),
                            $_POST['section'] ?? null, $_POST['hint'] ?? null,
                            (int)($_POST['sort_order'] ?? 0));
                set_flash('ok', '항목을 수정했습니다.');
                $redirect = 'admin.php?view=checklist&spot=' . (int)($_POST['spot_id'] ?? 0); break;
            case 'item_delete':
                delete_item((int)($_POST['id'] ?? 0));
                set_flash('ok', '항목을 삭제했습니다.');
                $redirect = 'admin.php?view=checklist&spot=' . (int)($_POST['spot_id'] ?? 0); break;

            // 완료 알림 메일 강제 발송
            case 'force_send':
                $ok = force_send_completion();
                set_flash($ok ? 'ok' : 'err',
                    $ok ? '완료 알림 메일을 발송했습니다.' : '메일 발송에 실패했습니다. (발신 설정 확인)');
                $redirect = 'admin.php'; break;

            // 발신(메일) 설정 저장
            case 'settings_save':
                foreach (['SMTP_HOST','SMTP_PORT','SMTP_USER','SMTP_FROM','SMTP_FROM_NAME','NOTIFY_TO','NOTIFY_TO_NAME'] as $k) {
                    set_setting($k, isset($_POST[$k]) ? trim((string)$_POST[$k]) : '');
                }
                // 비밀번호는 입력했을 때만 갱신(빈칸이면 기존 유지)
                if (isset($_POST['SMTP_PASS']) && trim((string)$_POST['SMTP_PASS']) !== '') {
                    set_setting('SMTP_PASS', trim((string)$_POST['SMTP_PASS']));
                }
                set_setting('MAIL_DRY_RUN', !empty($_POST['MAIL_DRY_RUN']) ? '1' : '0');
                set_flash('ok', '발신 설정을 저장했습니다.');
                $redirect = 'admin.php?view=settings'; break;

            // 테스트 메일 발송
            case 'settings_test':
                require_once __DIR__ . '/smtp.php';
                $terr = null;
                $tok = send_mail((string)mail_cfg('NOTIFY_TO'), (string)mail_cfg('NOTIFY_TO_NAME'),
                                 '[' . APP_TITLE . '] 테스트 메일', '<p>발신 설정 테스트 메일입니다.</p>', $terr);
                set_flash($tok ? 'ok' : 'err', $tok ? '테스트 메일을 발송했습니다.' : ('테스트 메일 실패: ' . h($terr)));
                $redirect = 'admin.php?view=settings'; break;

            default:
                throw new RuntimeException('알 수 없는 요청입니다.');
        }
    } catch (\Throwable $e) {
        set_flash('err', $e->getMessage());
    }
    header('Location: ' . $redirect); exit;
}

// ── 뷰 렌더링 ────────────────────────────────────────────
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
$view = $_GET['view'] ?? 'dashboard';
if (!in_array($view, ['dashboard', 'members', 'checklist', 'settings', 'member'], true)) $view = 'dashboard';
?>
<!DOCTYPE html>
<html lang="ko"><head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h(APP_TITLE) ?> — 관리자</title>
<link rel="stylesheet" href="assets/style.css">
<?php if ($view === 'dashboard'): ?><meta http-equiv="refresh" content="30"><!-- 30초 자동 갱신 --><?php endif; ?>
</head>
<body>
<header class="topbar">
  <div class="topbar-inner">
    <div>
      <div class="brand"><?= h(APP_TITLE) ?></div>
      <div class="who">관리자 대시보드<?= $view === 'dashboard' ? ' · 30초마다 자동 갱신' : '' ?></div>
    </div>
    <a class="logout" href="admin.php?logout=1">로그아웃</a>
  </div>
</header>

<nav class="admin-nav">
  <div class="admin-nav-inner">
    <a class="navlink <?= $view === 'dashboard' || $view === 'member' ? 'active' : '' ?>" href="admin.php">📊 대시보드</a>
    <a class="navlink <?= $view === 'members' ? 'active' : '' ?>" href="admin.php?view=members">👥 참가자 관리</a>
    <a class="navlink <?= $view === 'checklist' ? 'active' : '' ?>" href="admin.php?view=checklist">🗂️ 체크리스트 관리</a>
    <a class="navlink <?= $view === 'settings' ? 'active' : '' ?>" href="admin.php?view=settings">⚙️ 발신 설정</a>
  </div>
</nav>

<main class="container">
  <?php if ($flash): ?>
    <div class="alert <?= $flash['type'] === 'ok' ? 'okmsg' : 'error' ?>" role="alert"><?= h($flash['msg']) ?></div>
  <?php endif; ?>
  <?php
    if ($view === 'members')        include __DIR__ . '/admin_members.php';
    elseif ($view === 'checklist')  include __DIR__ . '/admin_checklist.php';
    elseif ($view === 'settings')   include __DIR__ . '/admin_settings.php';
    elseif ($view === 'member')     include __DIR__ . '/admin_member.php';
    else                            include __DIR__ . '/admin_dashboard.php';
  ?>
</main>
</body></html>
