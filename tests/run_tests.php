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
/** 특정 탭(spot)의 필수 항목만 채운다 */
function answer_spot_required(int $memberId, int $spotId): void {
    $st = db()->prepare('SELECT id, type, options FROM items WHERE required = 1 AND spot_id = ?');
    $st->execute([$spotId]);
    foreach ($st->fetchAll() as $it) {
        $opts = explode('|', (string)$it['options']);
        save_response($memberId, (int)$it['id'], $it['type'] === 'radio' ? $opts[0] : '1', null);
    }
}
/** 모든 필수 항목(전 탭)을 채운다 */
function answer_all_required(int $memberId): void {
    foreach (db()->query('SELECT id, type, options FROM items WHERE required = 1') as $it) {
        $opts = explode('|', (string)$it['options']);
        save_response($memberId, (int)$it['id'], $it['type'] === 'radio' ? $opts[0] : '1', null);
    }
}
/** 한 탭(spot1)만 채우고 제출 */
function fill_one_tab_and_submit(int $memberId, ?bool $wc = null, ?string $vision = null): array {
    answer_spot_required($memberId, 1);
    return submit_member($memberId, '비장애', site_list()[0], $wc, $vision);
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

echo "\n== 항목 수 / 응답 저장 ==\n";
test('전체 6 / 필수 5, 응답 저장·삭제·예외', function () {
    reset_test_db();
    eq(6, total_item_count());
    eq(5, required_item_count());
    $ids = required_ids();
    save_response(1, $ids[0], '보통', null);
    eq('보통', member_responses(1)[$ids[0]]['value']);
    save_response(1, $ids[3], '적합', '손잡이 보수');
    eq('손잡이 보수', member_responses(1)[$ids[3]]['note']);
    save_response(1, $ids[0], '', '');
    truthy(!isset(member_responses(1)[$ids[0]]));
    throws(fn() => save_response(1, 99999, '값', null), '존재하지 않는 항목');
});

echo "\n== 탭별 진행 & 한 탭 완료 ==\n";
test('탭별 진행률', function () {
    reset_test_db();
    $sp = member_spot_progress(1);
    eq(3, $sp[1]['total']); eq(2, $sp[2]['total']);
    truthy(!$sp[1]['complete']);
    answer_spot_required(1, 1);       // 설문 탭만 완료
    $sp = member_spot_progress(1);
    truthy($sp[1]['complete']); truthy(!$sp[2]['complete']);
});
test('한 탭만 완료해도 제출 가능(완화)', function () {
    reset_test_db();
    truthy(!member_any_tab_complete(1));
    answer_spot_required(1, 1);
    truthy(member_any_tab_complete(1));
    truthy(!member_progress(1)['complete']);   // 아직 전 탭은 미완료
});

echo "\n== 제출 & 잠금 ==\n";
test('완료 탭 없으면 제출 예외', function () {
    reset_test_db();
    throws(fn() => submit_member(1, '비장애', site_list()[0]), '완료한 탭');
    truthy(!is_submitted(1));
});
test('한 탭 완료 후 제출 성공 + 조사원 세부 저장', function () {
    reset_test_db();
    $res = fill_one_tab_and_submit(1, true, '저시력');
    truthy($res['ok']); truthy(is_submitted(1));
    $s = member_submission(1);
    eq(1, (int)$s['wheelchair']);
    eq('저시력', $s['vision_detail']);
    eq(site_list()[0], $s['site_name']);
});
test('잘못된 시각세부는 무시(null), 휠체어 false=0', function () {
    reset_test_db();
    fill_one_tab_and_submit(2, false, '이상한값');
    $s = member_submission(2);
    eq(0, (int)$s['wheelchair']);
    is_null_($s['vision_detail']);
});
test('제출 멱등 / 잘못된 관광지 거부', function () {
    reset_test_db();
    fill_one_tab_and_submit(1);
    truthy(submit_member(1, '비장애', site_list()[0])['ok']);
    eq(1, team_submitted_count(1));
    reset_test_db();
    answer_spot_required(3, 1);
    throws(fn() => submit_member(3, '비장애', '없는관광지'), '유효한 관광지');
});
test('제출 후에도 응답·메타 수정 가능(구글폼 방식)', function () {
    reset_test_db();
    fill_one_tab_and_submit(1, false, null);
    truthy(is_submitted(1));
    // 응답 수정 (제출 후에도 저장됨)
    $ids = required_ids();
    save_response(1, $ids[0], '매우 그렇다', null);
    eq('매우 그렇다', member_responses(1)[$ids[0]]['value']);
    // 메타 수정(재제출) — 중복 아님
    $res = submit_member(1, '시각장애', site_list()[1], true, '전맹');
    truthy($res['edited']);
    $s = member_submission(1);
    eq('시각장애', $s['surveyor_type']);
    eq(site_list()[1], $s['site_name']);
    eq(1, (int)$s['wheelchair']);
    eq('전맹', $s['vision_detail']);
    eq(1, team_submitted_count(1));
});

echo "\n== 조(팀) 완료 → 조별 메일(dry-run) & 중복 방지 ==\n";
test('임계값 미만이면 완료 아님', function () {
    reset_test_db();
    for ($i = 1; $i <= 4; $i++) fill_one_tab_and_submit($i);   // 1조 4명
    eq(4, team_submitted_count(1));
    truthy(!team_is_complete(1));
    is_null_(check_and_notify_team(1));
});
test('5명 제출 시 조 완료 + 메일 + 조원현황', function () {
    reset_test_db();
    for ($i = 1; $i <= 4; $i++) fill_one_tab_and_submit($i);
    $res = fill_one_tab_and_submit(5);                          // 5명째
    truthy(team_is_complete(1));
    truthy($res['notice'] !== null, '완료 알림');
    $row = db()->query('SELECT * FROM completions WHERE team_id = 1')->fetch();
    truthy($row); eq(1, (int)$row['email_sent']);
    truthy(file_exists(DRYRUN_LOG));
    contains(file_get_contents(DRYRUN_LOG), '제출 완료', '메일 제목');
});
test('조별 완료 메일 중복 방지 + 다른 조 독립', function () {
    reset_test_db();
    for ($i = 1; $i <= 5; $i++) fill_one_tab_and_submit($i);
    is_null_(check_and_notify_team(1), '재확인은 null');
    truthy(!team_is_complete(2), '2조는 독립');
});
test('조원별 제출 현황', function () {
    reset_test_db();
    fill_one_tab_and_submit(1);
    $st = team_members_status(1);
    eq(6, count($st));
    truthy($st[0]['submitted']);       // 1번(홍길동) 제출
    truthy(!$st[1]['submitted']);      // 2번 미제출
});
test('우리 조 진행 매트릭스', function () {
    reset_test_db();
    answer_spot_required(1, 1);        // 홍길동(1) 설문 탭만 완료
    $mx = team_progress_matrix(1);
    eq(5, count($mx['spots']));
    eq(6, count($mx['members']));      // 1조 6명
    $row = $mx['members'][0];          // 홍길동
    eq(3, $row['cells'][1]['total']);
    truthy($row['cells'][1]['complete']);   // 설문 탭 완료
    truthy(!$row['cells'][2]['complete']);  // 이동권 미완료
});

echo "\n== 관리자 조별 강제 발송 ==\n";
test('조별 강제 발송(dry-run)', function () {
    reset_test_db();
    truthy(force_send_team_completion(2));
    eq(1, (int)db()->query('SELECT email_sent FROM completions WHERE team_id = 2')->fetch()['email_sent']);
});

echo "\n== 접속 허용 기간 ==\n";
test('현재 기간 안이면 접속 허용 + 안내문구', function () {
    // bootstrap 이 ACCESS_START/END 를 현재 포함 창으로 정의함
    truthy(participant_access_open());
    truthy(access_window_text() !== '');
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

echo "\n== CSRF / 관리자 비밀번호 / 설정 ==\n";
test('CSRF + 비밀번호 + 발신설정 오버라이드', function () {
    reset_test_db();
    unset($_SESSION['csrf']);
    $t = csrf_token();
    truthy(csrf_verify($t)); truthy(!csrf_verify('bad')); truthy(!csrf_verify(null));
    truthy(hash_equals(ADMIN_PASSWORD, 'test-admin-pw'));
    eq(NOTIFY_TO, mail_cfg('NOTIFY_TO'));
    set_setting('NOTIFY_TO', 'boss@example.com');
    eq('boss@example.com', mail_cfg('NOTIFY_TO'));
});

// ── 결과 요약 ──────────────────────────────────────────────
$pass = $GLOBALS['__pass']; $fail = $GLOBALS['__fail'];
echo "\n" . str_repeat('─', 46) . "\n";
echo "결과: {$pass} 통과, {$fail} 실패 (총 " . ($pass + $fail) . ")\n";
if ($fail > 0) { echo "실패:\n"; foreach ($GLOBALS['__fails'] as $f) echo "  - {$f}\n"; exit(1); }
echo "\xF0\x9F\x8E\x89 모든 테스트 통과!\n";
exit(0);
