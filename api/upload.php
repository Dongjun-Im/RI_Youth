<?php
/** 사진/영상 업로드 — 참가자가 각 탭(관광지) 아래에 파일 업로드.
 *  form POST (multipart/form-data). 성공/실패 시 flash 남기고 app.php 로 리다이렉트.
 *  제출 완료(잠금) 상태에서는 업로드 불가.
 */
require_once __DIR__ . '/../lib.php';

$me = current_member();
if (!$me) { header('Location: ../index.php'); exit; }
$memberId = (int)$me['id'];

$spotId = (int)($_POST['spot_id'] ?? 0);
$back   = 'app.php' . ($spotId > 0 ? '#panel-' . $spotId : '');

try {
    if (!participant_access_open()) {
        throw new RuntimeException('지금은 조사 접속 기간이 아닙니다. (' . access_window_text() . ')');
    }
    if (!csrf_verify($_POST['csrf'] ?? null)) {
        throw new RuntimeException('보안 토큰이 만료되었습니다. 새로고침 후 다시 시도하세요.');
    }
    // 제출 후에도 (접속 기간 내) 파일 추가·삭제 가능 — 구글폼 방식
    // spot_id 유효성
    $st = db()->prepare('SELECT 1 FROM spots WHERE id = ?');
    $st->execute([$spotId]);
    if (!$st->fetchColumn()) {
        throw new RuntimeException('잘못된 탭 정보입니다.');
    }
    if (empty($_FILES['file']) || ($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        throw new RuntimeException('파일을 선택하지 않았습니다. 업로드는 선택 사항이니, 원하실 때만 파일을 골라 업로드해 주세요.');
    }
    upload_save($memberId, $spotId, $_FILES['file']);
    $_SESSION['flash'] = ['type' => 'ok', 'msg' => '파일을 업로드했습니다.'];
} catch (\Throwable $e) {
    $_SESSION['flash'] = ['type' => 'err', 'msg' => $e->getMessage()];
}
header('Location: ../' . $back); exit;
