<?php
/**
 * 열린관광지 모니터링 앱 — 테스트 스위트 (외부 라이브러리 불필요)
 * 실행:  C:\xampp\php\php.exe tests\run_tests.php   또는  tests\run.bat
 */
require_once __DIR__ . '/bootstrap.php';

// ── 미니 테스트 프레임워크 ─────────────────────────────────
$GLOBALS['__pass'] = 0; $GLOBALS['__fail'] = 0; $GLOBALS['__fails'] = [];
function test(string $name, callable $fn): void {
    try { $fn(); $GLOBALS['__pass']++; echo "  \xE2\x9C\x94 {$name}\n"; }
    catch (\Throwable $e) {
        $GLOBALS['__fail']++; $GLOBALS['__fails'][] = $name;
        echo "  \xE2\x9C\x98 {$name}\n      -> " . $e->getMessage() . "\n";
    }
}
class AssertionFailed extends \Exception {}
function eq($expected, $actual, string $msg = ''): void {
    if ($expected !== $actual) throw new AssertionFailed(($msg ? $msg . ' | ' : '') .
        '기대 ' . var_export($expected, true) . ', 실제 ' . var_export($actual, true));
}
function truthy($v, string $msg = 'truthy 기대'): void { if (!$v) throw new AssertionFailed($msg); }
function is_null_($v, string $msg = 'null 기대'): void { if ($v !== null) throw new AssertionFailed($msg . ' | 실제 ' . var_export($v, true)); }
function contains(string $h, string $n, string $m = ''): void {
    if (mb_strpos($h, $n) === false) throw new AssertionFailed(($m ? $m . ' | ' : '') . "'{$n}' 없음");
}
function throws(callable $fn, string $needle = '', string $m = ''): void {
    try { $fn(); } catch (\Throwable $e) {
        if ($needle !== '' && mb_strpos($e->getMessage(), $needle) === false)
            throw new AssertionFailed(($m ? $m . ' | ' : '') . "예외 메시지에 '{$needle}' 없음: " . $e->getMessage());
        return;
    }
    throw new AssertionFailed(($m ? $m . ' | ' : '') . '예외가 발생해야 함');
}

// ── 도우미 ─────────────────────────────────────────────────
function required_ids(): array {
    return array_map('intval', array_column(
        db()->query('SELECT id FROM items WHERE required = 1 ORDER BY id')->fetchAll(), 'id'));
}
function answer_all_required(int $memberId): void {
    foreach (db()->query('SELECT id, type, options FROM items WHERE required = 1') as $it) {
        $opts = explode('|', (string)$it['options']);
        save_response($memberId, (int)$it['id'], $it['type'] === 'radio' ? $opts[0] : '1', null);
    }
}
/** 참가자들이 필수 관광지(1~N)를 각각 커버하도록 응답+제출. 반환: 마지막 notice */
function cover_required_sites(): ?string {
    $sites = required_sites();
    $mids  = array_map('intval', array_column(db()->query('SELECT id FROM members ORDER BY id')->fetchAll(), 'id'));
    $notice = null;
    foreach ($sites as $i => $s) {
        if (!isset($mids[$i])) break;
        answer_all_required($mids[$i]);
        $r = submit_member($mids[$i], '비장애', $s);
        if ($r['notice']) $notice = $r['notice'];
    }
    return $notice;
}

// ══════════════════════════════════════════════════════════
echo "\n== 휴대폰번호 정규화 / 로그인 ==\n";
reset_test_db();
test('정규화 + 로그인 성공/실패', function () {
    eq('01011110001', normalize_phone('010-1111-0001'));
    eq('', normalize_phone('abc'));
    reset_test_db();
    $m = login_by_phone('010-1111-0001'); truthy($m); eq('홍길동', $m['name']);
    reset_test_db();
    is_null_(login_by_phone('01099998888'));
    is_null_(login_by_phone(''));
});

echo "\n== 항목 수 ==\n";
test('전체 6개 / 필수 5개', function () {
    reset_test_db();
    eq(6, total_item_count());
    eq(5, required_item_count());
});

echo "\n== 응답 저장 ==\n";
test('라디오/개선사항 저장, 비우면 삭제, 없는 항목 예외', function () {
    reset_test_db();
    $ids = required_ids();
    save_response(1, $ids[0], '보통', null);
    eq('보통', member_responses(1)[$ids[0]]['value']);
    save_response(1, $ids[3], '적합', '손잡이 보수 필요');
    eq('손잡이 보수 필요', member_responses(1)[$ids[3]]['note']);
    save_response(1, $ids[0], '', '');
    truthy(!isset(member_responses(1)[$ids[0]]));
    throws(fn() => save_response(1, 99999, '값', null), '존재하지 않는 항목');
});

echo "\n== 진행률(개인별) ==\n";
test('초기 0 → 부분 → 전체 완료, 선택 텍스트 제외', function () {
    reset_test_db();
    $p = member_progress(1);
    eq(0, $p['done']); eq(5, $p['total']); eq(5, $p['remaining']); truthy(!$p['complete']);
    $ids = required_ids();
    save_response(1, $ids[0], '보통', null);
    save_response(1, $ids[1], '그렇다', null);
    $p = member_progress(1); eq(2, $p['done']); eq(3, $p['remaining']); eq(40, $p['pct']);
    // 선택(비필수) 텍스트는 진행률 무관
    $textId = (int)db()->query("SELECT id FROM items WHERE type='text'")->fetchColumn();
    save_response(1, $textId, '좋았습니다', null);
    eq(2, member_progress(1)['done']);
    answer_all_required(1);
    $p = member_progress(1); eq(0, $p['remaining']); truthy($p['complete']);
});

echo "\n== 제출 & 잠금 ==\n";
test('미완료 제출은 예외', function () {
    reset_test_db();
    throws(fn() => submit_member(1, '비장애', site_list()[0]), '남은 항목');
    truthy(!is_submitted(1));
});
test('완료 후 제출 성공 + 잠금 + 정보 저장', function () {
    reset_test_db();
    $site = site_list()[0];
    answer_all_required(1);
    $res = submit_member(1, '시각장애', $site);
    truthy($res['ok']); truthy(is_submitted(1));
    $s = member_submission(1);
    eq('시각장애', $s['surveyor_type']);
    eq($site, $s['site_name']);
});
test('제출 멱등', function () {
    reset_test_db();
    answer_all_required(1);
    submit_member(1, '비장애', site_list()[0]);
    truthy(submit_member(1, '비장애', site_list()[0])['ok']);
    eq(1, submitted_count());
});
test('유효하지 않은 관광지는 제출 거부', function () {
    reset_test_db();
    answer_all_required(1);
    throws(fn() => submit_member(1, '비장애', '없는관광지'), '유효한 관광지');
});

echo "\n== 관광지 커버 완료 → 메일(dry-run) & 중복 방지 ==\n";
test('필수 관광지 일부만 커버면 완료 아님', function () {
    reset_test_db();
    $sites = site_list();
    answer_all_required(1); submit_member(1, '비장애', $sites[0]);
    answer_all_required(2); submit_member(2, '비장애', $sites[1]);
    truthy(!sites_complete());
    is_null_(check_and_notify_completion_sites());
});
test('관광지 1~4 커버 시 완료 메일 발송 + 기록', function () {
    reset_test_db();
    $notice = cover_required_sites();
    truthy(sites_complete());
    truthy($notice !== null, '완료 알림 메시지');
    $row = db()->query('SELECT * FROM completions WHERE id = 1')->fetch();
    truthy($row); eq(1, (int)$row['email_sent']);
    truthy(file_exists(DRYRUN_LOG));
    contains(file_get_contents(DRYRUN_LOG), '완료 알림', '메일 제목 기록');
});
test('완료 메일 중복 발송 방지', function () {
    reset_test_db();
    cover_required_sites();
    is_null_(check_and_notify_completion_sites());
});
test('필수 관광지 개수는 4', function () {
    reset_test_db();
    eq(4, count(required_sites()));
    eq(5, count(site_list()));
});

echo "\n== 관리자 강제 발송 ==\n";
test('커버 전이어도 강제 발송 성공(dry-run)', function () {
    reset_test_db();
    truthy(force_send_completion());
    eq(1, (int)db()->query('SELECT email_sent FROM completions WHERE id = 1')->fetch()['email_sent']);
});

echo "\n== 발신 설정(settings) ==\n";
test('설정 저장/조회 + 메일 설정 오버라이드', function () {
    reset_test_db();
    eq(NOTIFY_TO, mail_cfg('NOTIFY_TO'));                 // 기본값 = config 상수
    set_setting('NOTIFY_TO', 'boss@example.com');
    eq('boss@example.com', mail_cfg('NOTIFY_TO'));        // settings 우선
    set_setting('SMTP_FROM', 'sender@example.com');
    eq('sender@example.com', mail_cfg('SMTP_FROM'));
});

echo "\n== 항목 관리(유형/선택지) ==\n";
test('라디오 선택지 필수 / 생성·옵션·삭제 CASCADE', function () {
    reset_test_db();
    throws(fn() => create_item(1, '문항', 'radio', '', true, false, null, null, 9), '선택지');
    $id = create_item(1, '새 문항', 'radio', '적합|애매|미흡', true, true, 'Ⅰ 섹션', '도움말', 9);
    $it = db()->query("SELECT * FROM items WHERE id = $id")->fetch();
    eq('radio', $it['type']); eq(1, (int)$it['has_note']);
    eq(['적합', '애매', '미흡'], item_options($it));
    $ids = required_ids();
    save_response(1, $ids[0], '보통', null);
    delete_item($ids[0]);
    truthy(!isset(member_responses(1)[$ids[0]]));
});

echo "\n== 조별 제출 순위 ==\n";
test('제출 인원수 내림차순 + 동점', function () {
    reset_test_db();
    $s = site_list();
    // 1조(2명) 제출, 2조(1명) 제출
    answer_all_required(1); submit_member(1, '비장애', $s[0]);
    answer_all_required(2); submit_member(2, '비장애', $s[1]);
    answer_all_required(3); submit_member(3, '비장애', $s[2]);
    $b = leaderboard();
    eq('1조', $b[0]['name']); eq(2, $b[0]['submitted']); eq(1, $b[0]['rank']);
});

echo "\n== 조/참가자 관리 (회귀) ==\n";
test('조 삭제 CASCADE / 중복 휴대폰 거부 / CSV 임포트', function () {
    reset_test_db();
    $tid = create_team('삭제조'); create_member($tid, '임시', '01099990001');
    $before = count(list_members()); delete_team($tid);
    eq($before - 1, count(list_members()));
    throws(fn() => create_member(1, '중복', '010-1111-0001'), '이미 등록');
    $rep = import_members_csv([['새조','김일','010-2000-0001'],['1조','박중복','010-1111-0001'],['','무조','01030000000']]);
    eq(1, $rep['added']); eq(1, $rep['dup']); eq(1, $rep['error']);
});

echo "\n== CSRF / 관리자 비밀번호 ==\n";
test('CSRF + 비밀번호', function () {
    unset($_SESSION['csrf']);
    $t = csrf_token();
    truthy(csrf_verify($t)); truthy(!csrf_verify('bad')); truthy(!csrf_verify(null));
    truthy(hash_equals(ADMIN_PASSWORD, 'test-admin-pw'));
    truthy(!hash_equals(ADMIN_PASSWORD, 'wrong'));
});

// ── 결과 요약 ──────────────────────────────────────────────
$pass = $GLOBALS['__pass']; $fail = $GLOBALS['__fail'];
echo "\n" . str_repeat('─', 46) . "\n";
echo "결과: {$pass} 통과, {$fail} 실패 (총 " . ($pass + $fail) . ")\n";
if ($fail > 0) { echo "실패:\n"; foreach ($GLOBALS['__fails'] as $f) echo "  - {$f}\n"; exit(1); }
echo "\xF0\x9F\x8E\x89 모든 테스트 통과!\n";
exit(0);
