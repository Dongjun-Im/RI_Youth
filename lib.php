<?php
require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/** HTML 이스케이프 */
function h($s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
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
function submit_member(int $memberId, ?string $surveyorType, ?string $siteName): array {
    if (is_submitted($memberId)) return ['ok' => true, 'notice' => null]; // 멱등
    $p = member_progress($memberId);
    if (!$p['complete']) {
        throw new RuntimeException('아직 완료하지 않은 필수 항목이 있습니다. (남은 항목 ' . $p['remaining'] . '개)');
    }
    $site = $siteName !== null ? trim($siteName) : '';
    $sites = site_list();
    if ($sites && !in_array($site, $sites, true)) {
        throw new RuntimeException('유효한 관광지를 선택하세요.');
    }
    $ins = db()->prepare(
        sql_insert_ignore() . ' INTO submissions (member_id, surveyor_type, site_name, submitted_at)
         VALUES (?, ?, ?, NOW())');
    $ins->execute([$memberId,
                   $surveyorType !== null && trim($surveyorType) !== '' ? trim($surveyorType) : null,
                   $site !== '' ? $site : null]);
    return ['ok' => true, 'notice' => check_and_notify_completion_sites()];
}

// ── 전원 제출 완료 → 자동 메일(1회) ──────────────────────
function total_member_count(): int {
    return (int)db()->query('SELECT COUNT(*) FROM members')->fetchColumn();
}
function submitted_count(): int {
    return (int)db()->query('SELECT COUNT(*) FROM submissions')->fetchColumn();
}
/** 등록된 모든 참가자가 제출했는가? (참가자 ≥ 1) */
function all_submitted(): bool {
    $total = total_member_count();
    return $total > 0 && submitted_count() >= $total;
}

/**
 * 필수 관광지(1~4번)가 모두 커버되면 (처음 1회) 운영진에게 알림 메일 발송.
 * completions 테이블의 단일 행(id=1)으로 중복 발송 방지.
 * @return ?string 신규 완료 시 메시지, 아니면 null
 */
function check_and_notify_completion_sites(): ?string {
    if (!sites_complete()) return null;
    $ex = db()->query('SELECT email_sent FROM completions WHERE id = 1')->fetch();
    if ($ex) return null;
    $ins = db()->prepare(
        sql_insert_ignore() . ' INTO completions (id, completed_at, email_sent) VALUES (1, NOW(), 0)');
    $ins->execute();
    if ($ins->rowCount() === 0) return null; // 동시성: 다른 요청이 먼저 처리

    $err = null;
    $ok = send_completion_mail($err);
    return $ok
        ? '🎉 관광지 1~4번 모니터링 완료! 운영진에게 결과 알림 메일을 발송했습니다.'
        : '🎉 관광지 1~4번 모니터링 완료! (메일 발송 실패: ' . h($err) . ')';
}

/** 완료 메일 실제 발송 + completions(id=1) 결과 갱신. 발신/수신은 관리자 설정 우선. */
function send_completion_mail(?string &$err = null): bool {
    require_once __DIR__ . '/smtp.php';
    $subject = sprintf('[%s] 모니터링 조사 완료 알림', APP_TITLE);
    $body = completion_mail_body();
    $err = null;
    $ok = send_mail((string)mail_cfg('NOTIFY_TO'), (string)mail_cfg('NOTIFY_TO_NAME'), $subject, $body, $err);
    $upd = db()->prepare('UPDATE completions SET email_sent = ?, email_error = ? WHERE id = 1');
    $upd->execute([$ok ? 1 : 0, $ok ? null : mb_substr((string)$err, 0, 250)]);
    return $ok;
}

/** 관리자 강제 발송 (전원 제출 여부와 무관하게 완료 기록 후 발송) */
function force_send_completion(): bool {
    if (!db()->query('SELECT 1 FROM completions WHERE id = 1')->fetch()) {
        $ins = db()->prepare(sql_insert_ignore() . ' INTO completions (id, completed_at, email_sent) VALUES (1, NOW(), 0)');
        $ins->execute();
    }
    return send_completion_mail($e);
}
/** (호환용) 완료 메일 재발송 = 강제 발송 */
function resend_completion_mail(): bool { return force_send_completion(); }

/** 완료 알림 메일 본문(HTML) — 관광지별 제출 현황 요약 */
function completion_mail_body(): string {
    $title = h(APP_TITLE);
    $when  = date('Y-m-d H:i');
    $counts = site_submission_counts();
    $req    = required_sites();
    $subTotal = submitted_count();
    $trs = '';
    foreach (site_list() as $i => $s) {
        $c = $counts[$s] ?? 0;
        $isReq = in_array($s, $req, true);
        $mark  = $c > 0 ? '✅' : '⬜';
        $star  = $isReq ? ' <span style="color:#c0392b">*</span>' : '';
        $trs .= '<tr><td style="padding:6px;border-top:1px solid #eee">' . ($i + 1) . '. ' . h($s) . $star . '</td>'
              . '<td style="padding:6px;border-top:1px solid #eee">' . $mark . ' ' . $c . '건</td></tr>';
    }
    return <<<HTML
<div style="font-family:'Malgun Gothic',sans-serif;max-width:560px;margin:auto">
  <h2 style="color:#1a7f5a">✅ 모니터링 조사 완료 알림</h2>
  <p><strong>{$title}</strong></p>
  <table style="border-collapse:collapse;width:100%">
    <tr><td style="padding:6px;color:#666">기준 시각</td><td style="padding:6px">{$when}</td></tr>
    <tr><td style="padding:6px;color:#666">총 제출</td><td style="padding:6px"><strong>{$subTotal} 건</strong></td></tr>
  </table>
  <h3 style="color:#146147;margin:16px 0 4px">관광지별 제출 현황 <span style="color:#c0392b;font-size:12px">(* = 완료 필수)</span></h3>
  <table style="border-collapse:collapse;width:100%">
    <tr><th style="text-align:left;padding:6px;color:#666">관광지</th><th style="text-align:left;padding:6px;color:#666">제출</th></tr>
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
