<?php
/** 제출 API (개인별). 한 탭만 완료해도 제출 가능. 제출 시 잠금. */
require_once __DIR__ . '/../lib.php';
header('Content-Type: application/json; charset=utf-8');

$me = current_member();
if (!$me) { http_response_code(401); echo json_encode(['error' => '로그인 필요']); exit; }
if (!participant_access_open()) {
    http_response_code(403);
    echo json_encode(['error' => '지금은 조사 접속 기간이 아닙니다. (' . access_window_text() . ')']);
    exit;
}
$memberId = (int)$me['id'];

$in = json_decode(file_get_contents('php://input'), true) ?: [];
$surveyor   = trim((string)($in['surveyor_type'] ?? ''));
$site       = trim((string)($in['site_name'] ?? ''));
$wheelchair = array_key_exists('wheelchair', $in) ? (bool)$in['wheelchair'] : null;
$vision     = trim((string)($in['vision_detail'] ?? ''));

if ($surveyor === '') { http_response_code(400); echo json_encode(['error' => '조사원 구분을 선택하세요.']); exit; }
if ($site === '')     { http_response_code(400); echo json_encode(['error' => '관광지를 선택하세요.']); exit; }

try {
    $res = submit_member($memberId, $surveyor, $site, $wheelchair, $vision !== '' ? $vision : null);
} catch (\Throwable $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}

echo json_encode(['ok' => true, 'submitted' => true, 'notice' => $res['notice']]);
