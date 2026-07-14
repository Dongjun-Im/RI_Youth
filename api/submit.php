<?php
/** 제출 API (개인별). 모든 필수 항목 완료 + 조사원구분·관광지명 필요. 제출 시 잠금. */
require_once __DIR__ . '/../lib.php';
header('Content-Type: application/json; charset=utf-8');

$me = current_member();
if (!$me) { http_response_code(401); echo json_encode(['error' => '로그인 필요']); exit; }
$memberId = (int)$me['id'];

$in = json_decode(file_get_contents('php://input'), true) ?: [];
$surveyor = trim((string)($in['surveyor_type'] ?? ''));
$site     = trim((string)($in['site_name'] ?? ''));

if ($surveyor === '') { http_response_code(400); echo json_encode(['error' => '조사원 구분을 선택하세요.']); exit; }
if ($site === '')     { http_response_code(400); echo json_encode(['error' => '관광지를 선택하세요.']); exit; }

try {
    $res = submit_member($memberId, $surveyor, $site);
} catch (\Throwable $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}

echo json_encode(['ok' => true, 'submitted' => true, 'notice' => $res['notice']]);
