<?php
/** 응답 저장 API (개인별 자동저장). 제출 후에는 잠금되어 저장 불가. */
require_once __DIR__ . '/../lib.php';
header('Content-Type: application/json; charset=utf-8');

$me = current_member();
if (!$me) { http_response_code(401); echo json_encode(['error' => '로그인 필요']); exit; }
$memberId = (int)$me['id'];

// 접속 기간 밖에서는 저장 불가 (제출 후에는 기간 내 자유 수정 허용 — 구글폼 방식)
if (!participant_access_open()) {
    http_response_code(403);
    echo json_encode(['error' => '지금은 조사 접속 기간이 아닙니다.', 'locked' => true]);
    exit;
}

$in = json_decode(file_get_contents('php://input'), true) ?: [];
$itemId = (int)($in['item_id'] ?? 0);
$value  = array_key_exists('value', $in) && $in['value'] !== null ? (string)$in['value'] : null;
$note   = array_key_exists('note',  $in) && $in['note']  !== null ? (string)$in['note']  : null;

try {
    save_response($memberId, $itemId, $value, $note);
} catch (\Throwable $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}

echo json_encode([
    'ok'         => true,
    'progress'   => member_progress($memberId),
    'can_submit' => member_any_tab_complete($memberId),   // 한 탭만 완료해도 제출 가능
]);
