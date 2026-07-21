<?php
require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    // 모바일에서 로그인 세션이 잘 유지되도록 쿠키를 견고하게 설정.
    // (탭 절전/새로고침에도 로그인 유지 · HTTPS 환경이면 Secure)
    $__https = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
            || (($_SERVER['SERVER_PORT'] ?? '') == 443);
    @ini_set('session.gc_maxlifetime', '28800');   // 서버측 세션 수명 8시간
    @ini_set('session.use_strict_mode', '1');
    if (PHP_VERSION_ID >= 70300) {
        @session_set_cookie_params([
            'lifetime' => 28800,           // 쿠키 8시간 유지(브라우저 재시작에도)
            'path'     => '/',
            'secure'   => $__https,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    } else {
        @session_set_cookie_params(28800, '/', '', $__https, true);
    }
    @session_start();
}

/** HTML 이스케이프 */
function h($s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/** 자산 URL에 파일 수정시각을 버전으로 붙여 브라우저 캐시를 무효화 */
function asset_url(string $rel): string {
    $v = @filemtime(__DIR__ . '/' . ltrim($rel, '/'));
    return h($rel) . ($v ? '?v=' . $v : '');
}

/** 동적 페이지가 캐시(및 bfcache)되지 않도록 헤더 전송 (출력 전에 호출) */
function no_store_headers(): void {
    if (headers_sent()) return;
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}

// ── 참가자 접속 허용 기간 ─────────────────────────────────
/** 지금이 참가자 접속 허용 기간 안인가? (관리자는 이 제한을 받지 않음) */
function participant_access_open(): bool {
    $start = defined('ACCESS_START') ? (string)ACCESS_START : '';
    $end   = defined('ACCESS_END')   ? (string)ACCESS_END   : '';
    if ($start === '' || $end === '') return true;   // 미설정이면 제한 없음
    $now = time();
    return $now >= strtotime($start) && $now <= strtotime($end);
}
/** 접속 허용 기간 안내 문구 (예: 2026-07-23 ~ 2026-07-24) */
function access_window_text(): string {
    $s = defined('ACCESS_START') ? date('Y-m-d', strtotime((string)ACCESS_START)) : '';
    $e = defined('ACCESS_END')   ? date('Y-m-d', strtotime((string)ACCESS_END))   : '';
    return $s && $e ? "{$s} ~ {$e}" : '';
}
/** 접속 불가 안내 페이지를 출력하고 종료 (참가자 페이지 상단에서 호출) */
function render_access_closed(): void {
    no_store_headers();
    $title  = h(APP_TITLE);
    $window = h(access_window_text());
    http_response_code(403);
    echo <<<HTML
<!DOCTYPE html><html lang="ko"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>{$title} — 접속 기간 아님</title>
<link rel="stylesheet" href="assets/style.css">
</head><body class="login-body">
  <main class="login-card">
    <h1 class="login-title">{$title}</h1>
    <div class="alert error" role="alert" style="margin-top:12px">
      지금은 조사 접속 기간이 아닙니다.<br>
      <strong>조사 기간: {$window}</strong><br>
      기간 내에 다시 접속해 주세요.
    </div>
  </main>
</body></html>
HTML;
    exit;
}

/** 휴대폰번호 정규화: 숫자만 남김 */
function normalize_phone(string $raw): string {
    return preg_replace('/\D+/', '', $raw);
}

// ── 참가자 인증 ──────────────────────────────────────────
function current_member(): ?array {
    if (empty($_SESSION['member_id'])) return null;
    static $cache = null;
    if ($cache === null) {
        $st = db()->prepare(
            'SELECT m.*, t.name AS team_name
             FROM members m JOIN teams t ON t.id = m.team_id
             WHERE m.id = ?');
        $st->execute([$_SESSION['member_id']]);
        $cache = $st->fetch() ?: null;
    }
    return $cache;
}

function require_member(): array {
    $m = current_member();
    if (!$m) { header('Location: index.php'); exit; }
    return $m;
}

/** 휴대폰번호로 로그인 시도. 성공 시 member 반환, 실패 시 null */
function login_by_phone(string $rawPhone): ?array {
    $phone = normalize_phone($rawPhone);
    if ($phone === '') return null;
    $st = db()->prepare('SELECT id FROM members WHERE phone = ?');
    $st->execute([$phone]);
    $row = $st->fetch();
    if (!$row) return null;
    session_regenerate_id(true);
    $_SESSION['member_id'] = (int)$row['id'];
    return current_member();
}

// ── 관리자 인증 ──────────────────────────────────────────
function is_admin(): bool {
    return !empty($_SESSION['is_admin']);
}
function require_admin(): void {
    if (!is_admin()) { header('Location: admin.php'); exit; }
}

// ── 관리자 설정(발신 SMTP 등) ────────────────────────────
/** settings 테이블 값(비어있지 않으면) 반환, 없으면 $default. */
function setting(string $key, $default = null) {
    try {
        $st = db()->prepare('SELECT svalue FROM settings WHERE skey = ?');
        $st->execute([$key]);
        $v = $st->fetchColumn();
    } catch (\Throwable $e) { $v = false; }
    return ($v !== false && $v !== null && $v !== '') ? $v : $default;
}
function set_setting(string $key, ?string $value): void {
    $ex = db()->prepare('SELECT 1 FROM settings WHERE skey = ?');
    $ex->execute([$key]);
    if ($ex->fetch()) {
        $u = db()->prepare('UPDATE settings SET svalue = ? WHERE skey = ?');
        $u->execute([$value, $key]);
    } else {
        $i = db()->prepare('INSERT INTO settings (skey, svalue) VALUES (?, ?)');
        $i->execute([$key, $value]);
    }
}
/** 메일 설정값: settings 테이블 우선, 없으면 config.php 상수. */
function mail_cfg(string $key) {
    return setting($key, defined($key) ? constant($key) : null);
}

// ── 관광지 목록 & 완료 조건 ──────────────────────────────
/** 제출 시 선택 가능한 관광지 목록 */
function site_list(): array {
    $v = defined('SITE_LIST') ? SITE_LIST : [];
    if (is_array($v)) return array_values(array_filter(array_map('trim', $v), fn($s) => $s !== ''));
    return array_values(array_filter(array_map('trim', explode('|', (string)$v)), fn($s) => $s !== ''));
}
/** 완료에 필요한 관광지(앞에서부터 REQUIRED_SITE_COUNT 개 = 1~4번) */
function required_sites(): array {
    $all = site_list();
    $n = defined('REQUIRED_SITE_COUNT') ? (int)REQUIRED_SITE_COUNT : count($all);
    return array_slice($all, 0, max(0, $n));
}
/** 완료 제출이 1건 이상 존재하는 관광지 목록 */
function covered_sites(): array {
    $rows = db()->query("SELECT DISTINCT site_name FROM submissions WHERE site_name IS NOT NULL AND site_name <> ''")->fetchAll();
    return array_map(fn($r) => $r['site_name'], $rows);
}
/** 필수 관광지(1~N)가 모두 커버되었는가 */
function sites_complete(): bool {
    $req = required_sites();
    if (!$req) return false;
    $cov = covered_sites();
    foreach ($req as $s) if (!in_array($s, $cov, true)) return false;
    return true;
}
/** 관광지별 제출 인원 수 맵 */
function site_submission_counts(): array {
    $map = [];
    foreach (db()->query("SELECT site_name, COUNT(*) c FROM submissions WHERE site_name IS NOT NULL AND site_name <> '' GROUP BY site_name") as $r) {
        $map[$r['site_name']] = (int)$r['c'];
    }
    return $map;
}

// ── 항목 수 / 개인별 응답 & 진행률 ───────────────────────
/** 전체 체크리스트 항목 수 */
function total_item_count(): int {
    return (int)db()->query('SELECT COUNT(*) FROM items')->fetchColumn();
}
/** 필수 항목 수 (제출 요건 기준) */
function required_item_count(): int {
    return (int)db()->query('SELECT COUNT(*) FROM items WHERE required = 1')->fetchColumn();
}

/** 한 참가자의 응답 맵: item_id => ['value'=>, 'note'=>] */
function member_responses(int $memberId): array {
    $st = db()->prepare('SELECT item_id, value, note FROM responses WHERE member_id = ?');
    $st->execute([$memberId]);
    $map = [];
    foreach ($st->fetchAll() as $r) {
        $map[(int)$r['item_id']] = ['value' => $r['value'], 'note' => $r['note']];
    }
    return $map;
}

/**
 * 응답 저장(업서트). value·note 모두 비면 응답 삭제.
 * value = 라디오 선택값 / 텍스트 내용 / 체크 '1'. note = 개선사항(선택).
 */
function save_response(int $memberId, int $itemId, ?string $value, ?string $note = null): void {
    $chk = db()->prepare('SELECT 1 FROM items WHERE id = ?');
    $chk->execute([$itemId]);
    if (!$chk->fetch()) throw new InvalidArgumentException('존재하지 않는 항목입니다.');

    $value = $value === null ? null : trim($value);
    $note  = $note  === null ? null : trim($note);
    $emptyVal  = ($value === null || $value === '');
    $emptyNote = ($note  === null || $note  === '');

    if ($emptyVal && $emptyNote) {
        $del = db()->prepare('DELETE FROM responses WHERE member_id = ? AND item_id = ?');
        $del->execute([$memberId, $itemId]);
        return;
    }
    $vv = $emptyVal ? null : $value;
    $nn = $emptyNote ? null : $note;
    $ex = db()->prepare('SELECT id FROM responses WHERE member_id = ? AND item_id = ?');
    $ex->execute([$memberId, $itemId]);
    if ($ex->fetch()) {
        $up = db()->prepare('UPDATE responses SET value = ?, note = ?, updated_at = NOW() WHERE member_id = ? AND item_id = ?');
        $up->execute([$vv, $nn, $memberId, $itemId]);
    } else {
        $ins = db()->prepare('INSERT INTO responses (member_id, item_id, value, note, updated_at) VALUES (?, ?, ?, ?, NOW())');
        $ins->execute([$memberId, $itemId, $vv, $nn]);
    }
}

/** 참가자가 응답한 '필수' 항목 수 (value 채워진 것) */
function member_answered_count(int $memberId): int {
    $st = db()->prepare(
        "SELECT COUNT(*) FROM items i
         JOIN responses r ON r.item_id = i.id AND r.member_id = ?
         WHERE i.required = 1 AND r.value IS NOT NULL AND r.value <> ''");
    $st->execute([$memberId]);
    return (int)$st->fetchColumn();
}

/** 참가자 진행 상황 (남은 필수 항목 수 포함) */
function member_progress(int $memberId): array {
    $total = required_item_count();
    $done  = member_answered_count($memberId);
    $remaining = max(0, $total - $done);
    $pct = $total ? (int)round($done * 100 / $total) : 0;
    return ['done' => $done, 'total' => $total, 'remaining' => $remaining,
            'pct' => $pct, 'complete' => ($total > 0 && $remaining === 0)];
}

/**
 * 참가자의 탭(spot)별 진행 상황을 모두 반환.
 * 반환: [spot_id => ['total'=>N,'done'=>M,'remaining'=>K,'complete'=>bool], ...]
 * 필수 항목이 없는 탭은 complete=true 로 간주 (선택 항목만 있는 탭).
 */
function member_spot_progress(int $memberId): array {
    $stTotals = db()->query(
        'SELECT spot_id, COUNT(*) AS total
           FROM items
          WHERE required = 1
          GROUP BY spot_id');
    $totals = [];
    foreach ($stTotals as $row) $totals[(int)$row['spot_id']] = (int)$row['total'];

    $stDone = db()->prepare(
        "SELECT i.spot_id, COUNT(*) AS done
           FROM items i
           JOIN responses r ON r.item_id = i.id AND r.member_id = ?
          WHERE i.required = 1 AND r.value IS NOT NULL AND r.value <> ''
          GROUP BY i.spot_id");
    $stDone->execute([$memberId]);
    $dones = [];
    foreach ($stDone as $row) $dones[(int)$row['spot_id']] = (int)$row['done'];

    // 전체 탭 목록 (필수 항목이 없는 탭도 포함시켜야 표시가 자연스러움)
    $allSpots = db()->query('SELECT id FROM spots')->fetchAll(PDO::FETCH_COLUMN);

    $out = [];
    foreach ($allSpots as $spotId) {
        $spotId = (int)$spotId;
        $total = $totals[$spotId] ?? 0;
        $done  = $dones[$spotId] ?? 0;
        $remaining = max(0, $total - $done);
        $out[$spotId] = [
            'total' => $total,
            'done' => $done,
            'remaining' => $remaining,
            'complete' => ($remaining === 0),  // 필수 없는 탭도 complete=true
        ];
    }
    return $out;
}

/**
 * 제출 가능 여부(완화된 기준): 필수 항목이 있는 탭 중 '하나라도' 완료하면 true.
 * (기존: 모든 탭 완료 → 변경: 한 탭만 완료해도 제출 가능)
 */
function member_any_tab_complete(int $memberId): bool {
    foreach (member_spot_progress($memberId) as $sp) {
        if ($sp['total'] > 0 && $sp['complete']) return true;
    }
    return false;
}

// ── 팀(조) 단위 완료 ─────────────────────────────────────
function team_complete_threshold(): int {
    return defined('TEAM_COMPLETE_THRESHOLD') ? max(1, (int)TEAM_COMPLETE_THRESHOLD) : 5;
}
function team_member_count(int $teamId): int {
    $st = db()->prepare('SELECT COUNT(*) FROM members WHERE team_id = ?');
    $st->execute([$teamId]);
    return (int)$st->fetchColumn();
}
/** 조에서 제출한 인원 수 */
function team_submitted_count(int $teamId): int {
    $st = db()->prepare(
        'SELECT COUNT(*) FROM submissions s JOIN members m ON m.id = s.member_id WHERE m.team_id = ?');
    $st->execute([$teamId]);
    return (int)$st->fetchColumn();
}
/** 조가 완료(기준 인원 이상 제출)되었는가 */
function team_is_complete(int $teamId): bool {
    return team_submitted_count($teamId) >= team_complete_threshold();
}
/** 조원별 제출 여부 목록: [['id','name','submitted'(bool)], ...] */
function team_members_status(int $teamId): array {
    $st = db()->prepare(
        'SELECT m.id, m.name, (s.member_id IS NOT NULL) AS submitted
           FROM members m LEFT JOIN submissions s ON s.member_id = m.id
          WHERE m.team_id = ? ORDER BY m.id');
    $st->execute([$teamId]);
    $out = [];
    foreach ($st->fetchAll() as $r) {
        $out[] = ['id' => (int)$r['id'], 'name' => $r['name'], 'submitted' => (bool)$r['submitted']];
    }
    return $out;
}

/**
 * 조 전체의 조원별·탭별 진행 매트릭스 (참가자 '우리 조 진행상황' 페이지용).
 * @return array{spots: array, members: array} 각 member: id/name/submitted/cells[spot_id=>진행]/done/total
 */
function team_progress_matrix(int $teamId): array {
    $spots = db()->query('SELECT id, name FROM spots ORDER BY sort_order, id')->fetchAll();
    $st = db()->prepare(
        'SELECT m.id, m.name, (s.member_id IS NOT NULL) AS submitted
           FROM members m LEFT JOIN submissions s ON s.member_id = m.id
          WHERE m.team_id = ? ORDER BY m.id');
    $st->execute([$teamId]);
    $rows = [];
    foreach ($st->fetchAll() as $m) {
        $mid = (int)$m['id'];
        $sp  = member_spot_progress($mid);
        $cells = []; $totDone = 0; $totTotal = 0;
        foreach ($spots as $s) {
            $sid = (int)$s['id'];
            $p = $sp[$sid] ?? ['total' => 0, 'done' => 0, 'remaining' => 0, 'complete' => true];
            $cells[$sid] = $p;
            $totDone += $p['done']; $totTotal += $p['total'];
        }
        $rows[] = ['id' => $mid, 'name' => $m['name'], 'submitted' => (bool)$m['submitted'],
                   'cells' => $cells, 'done' => $totDone, 'total' => $totTotal];
    }
    return ['spots' => $spots, 'members' => $rows];
}

// ── 제출 & 잠금 ──────────────────────────────────────────
function member_submission(int $memberId): ?array {
    $st = db()->prepare('SELECT * FROM submissions WHERE member_id = ?');
    $st->execute([$memberId]);
    return $st->fetch() ?: null;
}
function is_submitted(int $memberId): bool {
    return member_submission($memberId) !== null;
}

/**
 * 참가자 제출. 모든 필수 항목이 채워져야 하며, 제출 후에는 잠금.
 * @return array{ok:bool, notice:?string}  notice: 전원 제출 완료 시 메시지
 */
function submit_member(int $memberId, ?string $surveyorType, ?string $siteName,
                       ?bool $wheelchair = null, ?string $visionDetail = null): array {
    $already = is_submitted($memberId);
    // 신규 제출일 때만 완화 게이트 확인 (한 탭이라도 완료하면 제출 가능)
    if (!$already && !member_any_tab_complete($memberId)) {
        throw new RuntimeException('아직 완료한 탭이 없습니다. 최소 한 개 탭의 필수 항목을 모두 완료해 주세요.');
    }
    $site  = $siteName !== null ? trim($siteName) : '';
    $sites = site_list();
    if ($sites && !in_array($site, $sites, true)) {
        throw new RuntimeException('유효한 관광지를 선택하세요.');
    }
    $vision = $visionDetail !== null && trim($visionDetail) !== '' ? trim($visionDetail) : null;
    if ($vision !== null && !in_array($vision, ['전맹', '저시력'], true)) $vision = null;
    $wc   = $wheelchair === null ? null : ($wheelchair ? 1 : 0);
    $surv = $surveyorType !== null && trim($surveyorType) !== '' ? trim($surveyorType) : null;
    $sn   = $site !== '' ? $site : null;

    if ($already) {
        // 구글폼 방식: 이미 제출됨 → 메타 정보만 수정 (응답은 자동 저장으로 반영)
        $upd = db()->prepare(
            'UPDATE submissions SET surveyor_type = ?, wheelchair = ?, vision_detail = ?, site_name = ? WHERE member_id = ?');
        $upd->execute([$surv, $wc, $vision, $sn, $memberId]);
        return ['ok' => true, 'notice' => null, 'edited' => true];
    }

    $ins = db()->prepare(
        sql_insert_ignore() . ' INTO submissions
            (member_id, surveyor_type, wheelchair, vision_detail, site_name, submitted_at)
         VALUES (?, ?, ?, ?, ?, NOW())');
    $ins->execute([$memberId, $surv, $wc, $vision, $sn]);

    // 이 참가자가 속한 조의 완료 여부 확인 → 완료 시 조별 메일
    $teamId = (int)db()->query('SELECT team_id FROM members WHERE id = ' . (int)$memberId)->fetchColumn();
    return ['ok' => true, 'notice' => check_and_notify_team($teamId), 'edited' => false];
}

// ── 제출 인원 집계 ───────────────────────────────────────
function total_member_count(): int {
    return (int)db()->query('SELECT COUNT(*) FROM members')->fetchColumn();
}
function submitted_count(): int {
    return (int)db()->query('SELECT COUNT(*) FROM submissions')->fetchColumn();
}

// ── 조 완료 → 조별 알림 메일(1회) ────────────────────────
/**
 * 조가 완료(기준 인원 이상 제출)되면 처음 1회 운영진에게 조별 알림 메일 발송.
 * completions(team_id) 로 조별 중복 발송 방지.
 * @return ?string 신규 완료 시 메시지, 아니면 null
 */
function check_and_notify_team(int $teamId): ?string {
    if ($teamId <= 0 || !team_is_complete($teamId)) return null;
    $chk = db()->prepare('SELECT 1 FROM completions WHERE team_id = ?');
    $chk->execute([$teamId]);
    if ($chk->fetch()) return null;   // 이미 처리됨
    $ins = db()->prepare(
        sql_insert_ignore() . ' INTO completions (team_id, completed_at, email_sent) VALUES (?, NOW(), 0)');
    $ins->execute([$teamId]);
    if ($ins->rowCount() === 0) return null;   // 동시성: 다른 요청이 먼저 처리

    $tn = db()->prepare('SELECT name FROM teams WHERE id = ?');
    $tn->execute([$teamId]);
    $teamName = (string)$tn->fetchColumn();
    $err = null;
    $ok = send_team_completion_mail($teamId, $err);
    $cnt = team_submitted_count($teamId);
    return $ok
        ? "🎉 {$teamName} 제출 완료! (제출 {$cnt}명) 운영진에게 알림 메일을 보냈습니다."
        : "🎉 {$teamName} 제출 완료! (메일 발송 실패: " . h($err) . ")";
}

/** 조 완료 메일 실제 발송 + completions(team_id) 결과 갱신. */
function send_team_completion_mail(int $teamId, ?string &$err = null): bool {
    require_once __DIR__ . '/smtp.php';
    $tn = db()->prepare('SELECT name FROM teams WHERE id = ?');
    $tn->execute([$teamId]);
    $teamName = (string)$tn->fetchColumn();
    $subject = sprintf('[%s] %s 제출 완료 알림', APP_TITLE, $teamName);
    $body = team_completion_mail_body($teamId);
    $err = null;
    $ok = send_mail((string)mail_cfg('NOTIFY_TO'), (string)mail_cfg('NOTIFY_TO_NAME'), $subject, $body, $err);
    $upd = db()->prepare('UPDATE completions SET email_sent = ?, email_error = ? WHERE team_id = ?');
    $upd->execute([$ok ? 1 : 0, $ok ? null : mb_substr((string)$err, 0, 250), $teamId]);
    return $ok;
}

/** 관리자 강제 발송(조별) — 완료 여부와 무관하게 완료기록 후 발송 */
function force_send_team_completion(int $teamId): bool {
    if ($teamId <= 0) return false;
    $chk = db()->prepare('SELECT 1 FROM completions WHERE team_id = ?');
    $chk->execute([$teamId]);
    if (!$chk->fetch()) {
        $ins = db()->prepare(sql_insert_ignore() . ' INTO completions (team_id, completed_at, email_sent) VALUES (?, NOW(), 0)');
        $ins->execute([$teamId]);
    }
    return send_team_completion_mail($teamId, $e);
}
/** (호환용) 재발송 = 조별 강제 발송 */
function resend_completion_mail(int $teamId = 0): bool { return force_send_team_completion($teamId); }

/** 조 완료 알림 메일 본문(HTML) — 조원 제출 현황 */
function team_completion_mail_body(int $teamId): string {
    $title = h(APP_TITLE);
    $when  = date('Y-m-d H:i');
    $tn = db()->prepare('SELECT name FROM teams WHERE id = ?');
    $tn->execute([$teamId]);
    $teamName  = h((string)$tn->fetchColumn());
    $threshold = team_complete_threshold();
    $sub   = team_submitted_count($teamId);
    $total = team_member_count($teamId);
    $trs = '';
    foreach (team_members_status($teamId) as $m) {
        $mark = $m['submitted'] ? '✅ 제출' : '⬜ 미제출';
        $trs .= '<tr><td style="padding:6px;border-top:1px solid #eee">' . h($m['name']) . '</td>'
              . '<td style="padding:6px;border-top:1px solid #eee">' . $mark . '</td></tr>';
    }
    return <<<HTML
<div style="font-family:'Malgun Gothic',sans-serif;max-width:560px;margin:auto">
  <h2 style="color:#1a7f5a">✅ {$teamName} 제출 완료</h2>
  <p><strong>{$title}</strong></p>
  <table style="border-collapse:collapse;width:100%">
    <tr><td style="padding:6px;color:#666">조</td><td style="padding:6px">{$teamName}</td></tr>
    <tr><td style="padding:6px;color:#666">완료 시각</td><td style="padding:6px">{$when}</td></tr>
    <tr><td style="padding:6px;color:#666">제출 인원</td><td style="padding:6px"><strong>{$sub} / {$total} 명</strong> (완료 기준 {$threshold}명 이상)</td></tr>
  </table>
  <h3 style="color:#146147;margin:16px 0 4px">조원 제출 현황</h3>
  <table style="border-collapse:collapse;width:100%">
    <tr><th style="text-align:left;padding:6px;color:#666">이름</th><th style="text-align:left;padding:6px;color:#666">상태</th></tr>
    {$trs}
  </table>
  <p style="color:#999;font-size:12px;margin-top:16px">이 메일은 시스템이 자동 발송했습니다.</p>
</div>
HTML;
}

// ══════════════════════════════════════════════════════════
//  관리자 기능 헬퍼 (참가자/조/관광지/항목 관리, CSV, 재발송, 순위)
//  — api/toggle.php·admin.php·테스트가 공유하는 순수 로직
// ══════════════════════════════════════════════════════════

// ── CSRF ─────────────────────────────────────────────────
/** 세션 CSRF 토큰 (없으면 생성) */
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}
/** 폼에서 전달된 토큰 검증 */
function csrf_verify(?string $token): bool {
    return !empty($_SESSION['csrf'])
        && is_string($token)
        && hash_equals($_SESSION['csrf'], $token);
}

// ── 조(팀) 관리 ──────────────────────────────────────────
function list_teams(): array {
    return db()->query('SELECT * FROM teams ORDER BY id')->fetchAll();
}
function create_team(string $name): int {
    $name = trim($name);
    if ($name === '') throw new InvalidArgumentException('조 이름을 입력하세요.');
    $st = db()->prepare('INSERT INTO teams (name) VALUES (?)');
    $st->execute([$name]);
    return (int)db()->lastInsertId();
}
function rename_team(int $id, string $name): void {
    $name = trim($name);
    if ($name === '') throw new InvalidArgumentException('조 이름을 입력하세요.');
    $st = db()->prepare('UPDATE teams SET name = ? WHERE id = ?');
    $st->execute([$name, $id]);
}
/** 조 삭제 (조원·체크·완료기록은 FK CASCADE 로 함께 삭제) */
function delete_team(int $id): void {
    $st = db()->prepare('DELETE FROM teams WHERE id = ?');
    $st->execute([$id]);
}

// ── 참가자 관리 ──────────────────────────────────────────
function list_members(): array {
    return db()->query(
        'SELECT m.*, t.name AS team_name
         FROM members m JOIN teams t ON t.id = m.team_id
         ORDER BY t.id, m.id')->fetchAll();
}
function find_member_by_phone(string $phone): ?array {
    $st = db()->prepare('SELECT * FROM members WHERE phone = ?');
    $st->execute([$phone]);
    return $st->fetch() ?: null;
}
function team_exists(int $teamId): bool {
    $st = db()->prepare('SELECT 1 FROM teams WHERE id = ?');
    $st->execute([$teamId]);
    return (bool)$st->fetch();
}
/** 참가자 추가. 휴대폰은 정규화 후 UNIQUE 검사. 실패 시 예외. */
function create_member(int $teamId, string $name, string $rawPhone): int {
    $name  = trim($name);
    $phone = normalize_phone($rawPhone);
    if ($name === '')  throw new InvalidArgumentException('이름을 입력하세요.');
    if ($phone === '') throw new InvalidArgumentException('휴대폰번호를 입력하세요.');
    if (!team_exists($teamId)) throw new InvalidArgumentException('존재하지 않는 조입니다.');
    if (find_member_by_phone($phone)) {
        throw new RuntimeException('이미 등록된 휴대폰번호입니다: ' . $phone);
    }
    $st = db()->prepare('INSERT INTO members (team_id, name, phone) VALUES (?, ?, ?)');
    $st->execute([$teamId, $name, $phone]);
    return (int)db()->lastInsertId();
}
function update_member(int $id, int $teamId, string $name, string $rawPhone): void {
    $name  = trim($name);
    $phone = normalize_phone($rawPhone);
    if ($name === '')  throw new InvalidArgumentException('이름을 입력하세요.');
    if ($phone === '') throw new InvalidArgumentException('휴대폰번호를 입력하세요.');
    if (!team_exists($teamId)) throw new InvalidArgumentException('존재하지 않는 조입니다.');
    $dup = find_member_by_phone($phone);
    if ($dup && (int)$dup['id'] !== $id) {
        throw new RuntimeException('이미 등록된 휴대폰번호입니다: ' . $phone);
    }
    $st = db()->prepare('UPDATE members SET team_id = ?, name = ?, phone = ? WHERE id = ?');
    $st->execute([$teamId, $name, $phone, $id]);
}
function delete_member(int $id): void {
    $st = db()->prepare('DELETE FROM members WHERE id = ?');
    $st->execute([$id]);
}

/**
 * CSV 일괄 등록. 각 행은 [조이름, 이름, 휴대폰].
 * 조 이름이 없으면 자동 생성, 휴대폰은 정규화·중복 건너뜀.
 * @return array{added:int,dup:int,error:int,errors:string[]}
 */
function import_members_csv(array $rows): array {
    $added = 0; $dup = 0; $error = 0; $errors = [];
    foreach ($rows as $i => $row) {
        $teamName = trim((string)($row[0] ?? ''));
        $name     = trim((string)($row[1] ?? ''));
        $phone    = normalize_phone((string)($row[2] ?? ''));
        if ($teamName === '' && $name === '' && $phone === '') continue; // 빈 줄 무시
        $lineNo = $i + 1;
        if ($teamName === '' || $name === '' || $phone === '') {
            $error++; $errors[] = "{$lineNo}행: 조/이름/휴대폰 중 누락"; continue;
        }
        try {
            $st = db()->prepare('SELECT id FROM teams WHERE name = ?');
            $st->execute([$teamName]);
            $tid = $st->fetchColumn();
            if ($tid === false) $tid = create_team($teamName);
            if (find_member_by_phone($phone)) { $dup++; continue; }
            create_member((int)$tid, $name, $phone);
            $added++;
        } catch (\Throwable $e) {
            $error++; $errors[] = "{$lineNo}행: " . $e->getMessage();
        }
    }
    return ['added' => $added, 'dup' => $dup, 'error' => $error, 'errors' => $errors];
}

// ── 관광지(탭) 관리 ──────────────────────────────────────
function list_spots(): array {
    return db()->query('SELECT * FROM spots ORDER BY sort_order, id')->fetchAll();
}
function create_spot(string $name, int $sortOrder = 0): int {
    $name = trim($name);
    if ($name === '') throw new InvalidArgumentException('관광지 이름을 입력하세요.');
    $st = db()->prepare('INSERT INTO spots (name, sort_order) VALUES (?, ?)');
    $st->execute([$name, $sortOrder]);
    return (int)db()->lastInsertId();
}
function update_spot(int $id, string $name, int $sortOrder): void {
    $name = trim($name);
    if ($name === '') throw new InvalidArgumentException('관광지 이름을 입력하세요.');
    $st = db()->prepare('UPDATE spots SET name = ?, sort_order = ? WHERE id = ?');
    $st->execute([$name, $sortOrder, $id]);
}
/** 관광지 삭제 (소속 항목·체크는 FK CASCADE) */
function delete_spot(int $id): void {
    $st = db()->prepare('DELETE FROM spots WHERE id = ?');
    $st->execute([$id]);
}

// ── 체크리스트 항목 관리 ─────────────────────────────────
function list_items_by_spot(int $spotId): array {
    $st = db()->prepare('SELECT * FROM items WHERE spot_id = ? ORDER BY sort_order, id');
    $st->execute([$spotId]);
    return $st->fetchAll();
}
/** 허용 항목 유형 */
function item_types(): array { return ['radio', 'check', 'text']; }

/**
 * 항목 추가.
 * @param string  $type    'radio'|'check'|'text'
 * @param ?string $options 라디오 선택지 ('|' 로 구분, 예: "적합|애매|미흡")
 * @param bool    $required 제출 필수 여부
 * @param bool    $hasNote  '개선사항' 서술란(선택 입력) 표시 여부
 * @param ?string $section  탭 안의 소제목(그룹 헤더)
 * @param ?string $hint     근거/기준 등 도움말
 */
function create_item(int $spotId, string $label, string $type = 'check', ?string $options = null,
                     bool $required = true, bool $hasNote = false, ?string $section = null,
                     ?string $hint = null, int $sortOrder = 0): int {
    $label = trim($label);
    if ($label === '') throw new InvalidArgumentException('항목 내용을 입력하세요.');
    if (!in_array($type, item_types(), true)) $type = 'check';
    if ($type === 'radio' && trim((string)$options) === '') {
        throw new InvalidArgumentException('라디오 항목은 선택지를 입력해야 합니다.');
    }
    $st = db()->prepare('SELECT 1 FROM spots WHERE id = ?');
    $st->execute([$spotId]);
    if (!$st->fetch()) throw new InvalidArgumentException('존재하지 않는 관광지입니다.');
    $ins = db()->prepare(
        'INSERT INTO items (spot_id, section, label, hint, type, options, has_note, required, sort_order)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $ins->execute([$spotId, $section ?: null, $label, $hint ?: null, $type,
                   $type === 'radio' ? trim((string)$options) : null,
                   $hasNote ? 1 : 0, $required ? 1 : 0, $sortOrder]);
    return (int)db()->lastInsertId();
}
function update_item(int $id, string $label, string $type = 'check', ?string $options = null,
                     bool $required = true, bool $hasNote = false, ?string $section = null,
                     ?string $hint = null, int $sortOrder = 0): void {
    $label = trim($label);
    if ($label === '') throw new InvalidArgumentException('항목 내용을 입력하세요.');
    if (!in_array($type, item_types(), true)) $type = 'check';
    if ($type === 'radio' && trim((string)$options) === '') {
        throw new InvalidArgumentException('라디오 항목은 선택지를 입력해야 합니다.');
    }
    $up = db()->prepare(
        'UPDATE items SET section = ?, label = ?, hint = ?, type = ?, options = ?, has_note = ?, required = ?, sort_order = ?
         WHERE id = ?');
    $up->execute([$section ?: null, $label, $hint ?: null, $type,
                  $type === 'radio' ? trim((string)$options) : null,
                  $hasNote ? 1 : 0, $required ? 1 : 0, $sortOrder, $id]);
}
/** 항목 유형별 사람이 읽는 이름 */
function item_type_label(string $type): string {
    return ['radio' => '선택(라디오)', 'check' => '체크', 'text' => '서술(텍스트)'][$type] ?? $type;
}
/** '|' 구분 옵션 문자열 → 배열 */
function item_options(array $item): array {
    if (($item['type'] ?? '') !== 'radio' || trim((string)($item['options'] ?? '')) === '') return [];
    return array_values(array_filter(array_map('trim', explode('|', (string)$item['options'])), fn($o) => $o !== ''));
}
function delete_item(int $id): void {
    $st = db()->prepare('DELETE FROM items WHERE id = ?');
    $st->execute([$id]);
}

// ── 조별 제출 순위(리더보드) ─────────────────────────────
/** 조별 제출 현황(제출자 수) 내림차순. members/submitted/pct/rank 포함 */
function leaderboard(): array {
    $rows = db()->query(
        'SELECT t.id, t.name,
                (SELECT COUNT(*) FROM members m WHERE m.team_id = t.id) AS members,
                (SELECT COUNT(*) FROM members m JOIN submissions s ON s.member_id = m.id
                  WHERE m.team_id = t.id) AS submitted
         FROM teams t
         ORDER BY submitted DESC, t.id')->fetchAll();
    $rank = 0; $prev = null; $seen = 0;
    foreach ($rows as &$r) {
        $r['members']   = (int)$r['members'];
        $r['submitted'] = (int)$r['submitted'];
        $r['pct'] = $r['members'] ? (int)round($r['submitted'] * 100 / $r['members']) : 0;
        $seen++;
        if ($prev === null || $r['submitted'] !== $prev) $rank = $seen;
        $r['rank'] = $rank; $prev = $r['submitted'];
    }
    return $rows;
}

// ══════════════════════════════════════════════════════════
//  관리자 비밀번호 (settings 테이블에 password_hash 저장, 없으면 config.php 상수)
// ══════════════════════════════════════════════════════════

/** 입력한 비밀번호가 현재 관리자 비밀번호와 일치하는지. */
function admin_pw_verify(string $pw): bool {
    $hash = (string)(setting('ADMIN_PASSWORD_HASH') ?? '');
    if ($hash !== '') {
        return password_verify($pw, $hash);
    }
    // 아직 해시가 없으면 config.php 상수와 평문 비교 (초기 상태 호환)
    return defined('ADMIN_PASSWORD') && hash_equals(ADMIN_PASSWORD, $pw);
}
/** 새 비밀번호로 갱신 (해시 저장). */
function admin_pw_set(string $newPw): void {
    if (strlen($newPw) < 8) {
        throw new RuntimeException('비밀번호는 최소 8자 이상이어야 합니다.');
    }
    set_setting('ADMIN_PASSWORD_HASH', password_hash($newPw, PASSWORD_DEFAULT));
}

// ══════════════════════════════════════════════════════════
//  사진/영상 업로드 (탭별 = 관광지 단위)
// ══════════════════════════════════════════════════════════

if (!defined('UPLOAD_DIR')) {
    define('UPLOAD_DIR', __DIR__ . DIRECTORY_SEPARATOR . 'uploads');
}
if (!defined('UPLOAD_URL_BASE')) {
    define('UPLOAD_URL_BASE', 'uploads');   // htdocs 기준 상대 URL
}
if (!defined('UPLOAD_MAX_BYTES')) {
    // 40MB — PHP 기본 upload_max_filesize/post_max_size(40M) 안쪽으로.
    define('UPLOAD_MAX_BYTES', 10 * 1024 * 1024);   // InfinityFree 무료 플랜 제한: 10MB
}

/** 허용 확장자 → mime 유형. 나머지는 거부. */
function upload_allowed_types(): array {
    return [
        'jpg'  => ['image', 'image/jpeg'],
        'jpeg' => ['image', 'image/jpeg'],
        'png'  => ['image', 'image/png'],
        'webp' => ['image', 'image/webp'],
        'heic' => ['image', 'image/heic'],
        'heif' => ['image', 'image/heif'],
        'gif'  => ['image', 'image/gif'],
        'mp4'  => ['video', 'video/mp4'],
        'mov'  => ['video', 'video/quicktime'],
        'm4v'  => ['video', 'video/x-m4v'],
        'webm' => ['video', 'video/webm'],
        '3gp'  => ['video', 'video/3gpp'],
    ];
}

/** 참가자 한 명의 지정 탭에 올려둔 파일 목록. spot_id=null 이면 전체. */
function uploads_for(int $memberId, ?int $spotId = null): array {
    if ($spotId === null) {
        $st = db()->prepare('SELECT * FROM uploads WHERE member_id = ? ORDER BY spot_id, id');
        $st->execute([$memberId]);
    } else {
        $st = db()->prepare('SELECT * FROM uploads WHERE member_id = ? AND spot_id = ? ORDER BY id');
        $st->execute([$memberId, $spotId]);
    }
    return $st->fetchAll();
}

/** 업로드 저장. 성공 시 uploads 행 id, 실패 시 예외. */
function upload_save(int $memberId, int $spotId, array $file): int {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $codes = [
            UPLOAD_ERR_INI_SIZE   => '파일이 서버 설정(upload_max_filesize) 을 초과했습니다.',
            UPLOAD_ERR_FORM_SIZE  => '파일이 폼 크기 제한을 초과했습니다.',
            UPLOAD_ERR_PARTIAL    => '파일이 일부만 전송됐습니다.',
            UPLOAD_ERR_NO_FILE    => '파일이 선택되지 않았습니다.',
            UPLOAD_ERR_NO_TMP_DIR => '임시 폴더가 없습니다.',
            UPLOAD_ERR_CANT_WRITE => '디스크에 쓸 수 없습니다.',
            UPLOAD_ERR_EXTENSION  => 'PHP 확장 모듈에 의해 차단됐습니다.',
        ];
        $msg = $codes[$file['error']] ?? '알 수 없는 업로드 오류';
        throw new RuntimeException("업로드 실패: {$msg}");
    }
    $size = (int)($file['size'] ?? 0);
    if ($size <= 0)               throw new RuntimeException('빈 파일은 올릴 수 없습니다.');
    if ($size > UPLOAD_MAX_BYTES) throw new RuntimeException('파일이 너무 큽니다. 40MB 이하로 올려주세요.');

    $orig = (string)($file['name'] ?? 'upload');
    $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
    $allowed = upload_allowed_types();
    if (!isset($allowed[$ext])) {
        throw new RuntimeException('사진(jpg/png/webp/heic/gif) 또는 영상(mp4/mov/webm/m4v/3gp) 파일만 올릴 수 있습니다.');
    }
    [$kind, $expectMime] = $allowed[$ext];

    // 실제 파일 내용의 mime 도 검사 (확장자만 바꾼 우회 방지)
    $tmp = (string)($file['tmp_name'] ?? '');
    if (!$tmp || !is_uploaded_file($tmp)) {
        throw new RuntimeException('임시 파일을 찾을 수 없습니다.');
    }
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $actualMime = $finfo ? (string)finfo_file($finfo, $tmp) : '';
    if ($finfo) finfo_close($finfo);
    if ($actualMime && strpos($actualMime, $kind === 'image' ? 'image/' : 'video/') !== 0) {
        // 파일 내용이 사진/영상이 아니면 거부
        throw new RuntimeException("파일 내용이 {$kind} 로 보이지 않습니다 (감지된 형식: {$actualMime}).");
    }

    // 저장 폴더: uploads/member_{id}/spot_{id}/
    $subDir = "member_{$memberId}" . DIRECTORY_SEPARATOR . "spot_{$spotId}";
    $absDir = UPLOAD_DIR . DIRECTORY_SEPARATOR . $subDir;
    if (!is_dir($absDir) && !@mkdir($absDir, 0775, true) && !is_dir($absDir)) {
        throw new RuntimeException("저장 폴더를 만들 수 없습니다: {$absDir}");
    }

    // 파일명 — 타임스탬프 + 랜덤 + 원래 확장자. 원래 이름은 DB 에만 보관.
    $safeExt = preg_replace('/[^a-z0-9]/', '', $ext) ?: 'bin';
    $stored = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $safeExt;
    $absPath = $absDir . DIRECTORY_SEPARATOR . $stored;
    if (!move_uploaded_file($tmp, $absPath)) {
        throw new RuntimeException('파일을 저장하는 중 오류가 발생했습니다.');
    }

    // DB 기록 (URL 은 forward-slash 로 저장)
    $relPath = str_replace(DIRECTORY_SEPARATOR, '/', $subDir . DIRECTORY_SEPARATOR . $stored);
    $ins = db()->prepare(
        'INSERT INTO uploads (member_id, spot_id, kind, orig_name, stored_path, mime, size_bytes) '
        . 'VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $ins->execute([
        $memberId, $spotId, $kind,
        mb_substr($orig, 0, 240),
        $relPath,
        $actualMime ?: $expectMime,
        $size,
    ]);
    return (int)db()->lastInsertId();
}

/** 업로드 삭제 — 소유자(member_id) 검증 후 파일 + DB 행 제거. */
function upload_delete(int $uploadId, int $memberId): bool {
    $st = db()->prepare('SELECT * FROM uploads WHERE id = ? AND member_id = ?');
    $st->execute([$uploadId, $memberId]);
    $row = $st->fetch();
    if (!$row) return false;
    $abs = UPLOAD_DIR . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, (string)$row['stored_path']);
    if (is_file($abs)) @unlink($abs);
    $d = db()->prepare('DELETE FROM uploads WHERE id = ?');
    $d->execute([$uploadId]);
    return true;
}

/** 브라우저용 URL (htdocs 기준). */
function upload_url(array $upload): string {
    return UPLOAD_URL_BASE . '/' . (string)$upload['stored_path'];
}

/** 사람이 읽는 파일 크기 표시. */
function upload_size_h(int $bytes): string {
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1024 * 1024) return round($bytes / 1024, 1) . ' KB';
    return round($bytes / 1024 / 1024, 1) . ' MB';
}
