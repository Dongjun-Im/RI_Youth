<?php
/** 업로드 삭제 — 참가자 본인이 올린 파일만 삭제 가능. (제출 후에도 접속 기간 내 삭제 가능) */
require_once __DIR__ . '/../lib.php';

$me = current_member();
if (!$me) { header('Location: ../index.php'); exit; }
$memberId = (int)$me['id'];

$uploadId = (int)($_POST['upload_id'] ?? 0);
$spotId   = (int)($_POST['spot_id']   ?? 0);
$back     = 'app.php' . ($spotId > 0 ? '#panel-' . $spotId : '');

try {
    if (!csrf_verify($_POST['csrf'] ?? null)) {
        throw new RuntimeException('보안 토큰이 만료되었습니다. 새로고침 후 다시 시도하세요.');
    }
    if (!participant_access_open()) {
        throw new RuntimeException('지금은 조사 접속 기간이 아닙니다.');
    }
    if (!upload_delete($uploadId, $memberId)) {
        throw new RuntimeException('해당 파일을 찾을 수 없거나 삭제할 권한이 없습니다.');
    }
    $_SESSION['flash'] = ['type' => 'ok', 'msg' => '파일을 삭제했습니다.'];
} catch (\Throwable $e) {
    $_SESSION['flash'] = ['type' => 'err', 'msg' => $e->getMessage()];
}
header('Location: ../' . $back); exit;
