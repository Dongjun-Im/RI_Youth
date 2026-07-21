<?php
require_once __DIR__ . '/lib.php';
no_store_headers();   // 모바일 캐시/bfcache로 옛 화면이 복원되지 않도록
if (!participant_access_open()) { render_access_closed(); }   // 접속 기간 제한
$me = require_member();
$memberId = (int)$me['id'];

$spots = db()->query('SELECT * FROM spots ORDER BY sort_order, id')->fetchAll();
$itemsBySpot = [];
foreach (db()->query('SELECT * FROM items ORDER BY spot_id, sort_order, id') as $it) {
    $itemsBySpot[$it['spot_id']][] = $it;
}
$responses  = member_responses($memberId);
$prog       = member_progress($memberId);
$spotProg   = member_spot_progress($memberId);   // 탭(관광지)별 완료 상태
$submission = member_submission($memberId);
$locked     = $submission !== null;   // 제출됨 (구글폼처럼 제출 후에도 수정 가능)
$dis        = '';                     // 제출 후에도 입력을 잠그지 않음 (수정 허용)
$flash      = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
$csrf       = csrf_token();
// 참가자가 지금까지 올린 파일 (spot 별로 묶기)
$uploads_by_spot = [];
foreach (uploads_for($memberId) as $u) {
    $uploads_by_spot[(int)$u['spot_id']][] = $u;
}

$surveyorTypes = ['지체·뇌병변장애', '시각장애', '청각장애', '지적·자폐성장애', '기타', '비장애'];
$curSurveyor   = $submission['surveyor_type'] ?? '';
$curSite       = $submission['site_name'] ?? '';
$curWheelchair = $submission['wheelchair'] ?? null;      // 1/0/null
$curVision     = $submission['vision_detail'] ?? '';     // 전맹/저시력

$canSubmit  = member_any_tab_complete($memberId);        // 한 탭만 완료해도 제출 가능
$teamId     = (int)$me['team_id'];
$teamStat   = team_members_status($teamId);              // 우리 조 팀원별 제출 현황
$teamSubCnt = 0; foreach ($teamStat as $ts) if ($ts['submitted']) $teamSubCnt++;
$teamThresh = team_complete_threshold();
$teamDone   = $teamSubCnt >= $teamThresh;
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= h(APP_TITLE) ?></title>
<link rel="stylesheet" href="<?= asset_url('assets/style.css') ?>">
</head>
<body>
<header class="topbar">
  <div class="topbar-inner">
    <div>
      <div class="brand"><?= h(APP_TITLE) ?></div>
      <div class="who"><?= h($me['team_name']) ?> · <?= h($me['name']) ?>님</div>
    </div>
    <a class="logout" href="logout.php">로그아웃</a>
  </div>
  <div class="progress-wrap">
    <div class="progress-bar">
      <div class="progress-fill" id="progressFill" style="width: <?= $prog['pct'] ?>%"></div>
    </div>
    <div class="progress-text">
      진행률 <strong id="progressPct"><?= $prog['pct'] ?>%</strong>
      (<span id="progressDone"><?= $prog['done'] ?></span>/<?= $prog['total'] ?>) ·
      남은 항목 <strong id="progressRemain"><?= $prog['remaining'] ?></strong>개
    </div>
  </div>
  <!-- 스크린리더 안내: 남은 항목 수를 실시간으로 읽어줌 -->
  <div id="remainLive" class="sr-only" role="status" aria-live="polite" aria-atomic="true">
    남은 항목 <?= $prog['remaining'] ?>개
  </div>
</header>

<main class="container" data-locked="0">
  <?php if ($flash): ?>
    <div class="alert <?= $flash['type'] === 'ok' ? 'okmsg' : 'error' ?>" role="alert"><?= h($flash['msg']) ?></div>
  <?php endif; ?>
  <div class="banner-done" id="doneBanner" <?= $locked ? '' : 'hidden' ?>>
    ✅ 제출 완료 — 응답은 <strong>계속 수정</strong>할 수 있어요. 변경은 자동 저장됩니다. (조사원 구분·관광지를 바꾸면 아래 ‘수정 저장’을 눌러주세요)
    <?php if ($curSite): ?><br><small>관광지: <?= h($curSite) ?><?= $curSurveyor ? ' · 조사원 구분: ' . h($curSurveyor) : '' ?></small><?php endif; ?>
  </div>

  <!-- 대분류 탭 -->
  <div class="tabs" role="tablist" aria-label="조사 항목 분류">
    <?php foreach ($spots as $i => $sp):
      $sid   = (int)$sp['id'];
      $sPg   = $spotProg[$sid] ?? ['total'=>0,'done'=>0,'remaining'=>0,'complete'=>true];
      $isDone= (bool)$sPg['complete'];
      // 스크린리더 및 시각 사용자 모두에게 완료 상태 안내
      if ($sPg['total'] === 0) {
          $statusText  = '필수 항목 없음';
          $statusClass = 'tab-status tab-status-none';
      } elseif ($isDone) {
          $statusText  = '완료';
          $statusClass = 'tab-status tab-status-ok';
      } else {
          $statusText  = '미완료 · 남은 항목 ' . $sPg['remaining'] . '개';
          $statusClass = 'tab-status tab-status-todo';
      }
      $ariaLabel = $sp['name'] . ' 탭, ' . $statusText;
    ?>
      <button class="tab<?= $i === 0 ? ' active' : '' ?><?= $isDone ? ' tab-complete' : ' tab-incomplete' ?>"
              role="tab"
              aria-selected="<?= $i === 0 ? 'true' : 'false' ?>"
              aria-controls="panel-<?= $sid ?>" id="tab-<?= $sid ?>"
              aria-label="<?= h($ariaLabel) ?>"
              data-target="panel-<?= $sid ?>">
        <span class="tab-name"><?= h($sp['name']) ?></span>
        <span class="<?= $statusClass ?>" aria-hidden="true">
          <?php if ($sPg['total'] === 0): ?>
            <span class="tab-status-icon">–</span> 필수 없음
          <?php elseif ($isDone): ?>
            <span class="tab-status-icon">✓</span> 완료
          <?php else: ?>
            <span class="tab-status-icon">●</span> 미완료 <span class="tab-status-remain">(<?= $sPg['remaining'] ?>개)</span>
          <?php endif; ?>
        </span>
      </button>
    <?php endforeach; ?>
  </div>

  <?php foreach ($spots as $i => $sp):
      $items = $itemsBySpot[$sp['id']] ?? [];
      $curSection = null; $num = 0; ?>
    <section class="panel<?= $i === 0 ? ' active' : '' ?>" id="panel-<?= (int)$sp['id'] ?>"
             role="tabpanel" aria-labelledby="tab-<?= (int)$sp['id'] ?>" <?= $i === 0 ? '' : 'hidden' ?>>
      <h2 class="panel-title"><?= h($sp['name']) ?></h2>
      <?php if (!$items): ?><p class="empty">등록된 항목이 없습니다.</p><?php endif; ?>
      <?php foreach ($items as $it):
          $id = (int)$it['id'];
          if (($it['section'] ?? '') !== $curSection) {
              $curSection = $it['section'] ?? '';
              $num = 0;
              if ($curSection !== '') echo '<h3 class="q-section">' . h($curSection) . '</h3>';
          }
          $num++;
          $rv   = $responses[$id]['value'] ?? '';
          $rn   = $responses[$id]['note'] ?? '';
          $opts = item_options($it);
          $req  = (int)$it['required'] === 1;
      ?>
        <?php if ($it['type'] === 'radio'): ?>
          <fieldset class="q q-radio" data-item="<?= $id ?>">
            <legend class="q-label"><span class="q-num"><?= $num ?>.</span> <?= h($it['label']) ?>
              <?php if ($req): ?><span class="req" aria-hidden="true">*</span><?php endif; ?></legend>
            <?php if (!empty($it['hint'])): ?><p class="q-hint"><?= h($it['hint']) ?></p><?php endif; ?>
            <div class="opts">
              <?php foreach ($opts as $oi => $opt): ?>
                <label class="opt">
                  <input type="radio" name="item_<?= $id ?>" value="<?= h($opt) ?>"
                         data-item="<?= $id ?>" <?= $rv === $opt ? 'checked' : '' ?> <?= $dis ?>>
                  <span class="opt-box" aria-hidden="true"></span>
                  <span class="opt-text"><?= h($opt) ?></span>
                </label>
              <?php endforeach; ?>
            </div>
            <?php if ((int)$it['has_note'] === 1): ?>
              <label class="note-wrap">개선 사항 (선택)
                <textarea class="note" data-item="<?= $id ?>" rows="2"
                          placeholder="개선이 필요한 점을 적어주세요" <?= $dis ?>><?= h($rn) ?></textarea>
              </label>
            <?php endif; ?>
          </fieldset>
        <?php elseif ($it['type'] === 'text'): ?>
          <div class="q q-textq">
            <label class="q-label" for="item_<?= $id ?>"><span class="q-num"><?= $num ?>.</span> <?= h($it['label']) ?>
              <?php if ($req): ?><span class="req" aria-hidden="true">*</span><?php endif; ?></label>
            <?php if (!empty($it['hint'])): ?><p class="q-hint"><?= h($it['hint']) ?></p><?php endif; ?>
            <textarea id="item_<?= $id ?>" class="ta" data-item="<?= $id ?>" rows="3"
                      placeholder="자유롭게 작성해 주세요" <?= $dis ?>><?= h($rv) ?></textarea>
          </div>
        <?php else: /* check */ ?>
          <div class="q q-checkq">
            <label class="chkline">
              <input type="checkbox" class="item-check" data-item="<?= $id ?>"
                     <?= $rv === '1' ? 'checked' : '' ?> <?= $dis ?>>
              <span class="chk-box" aria-hidden="true"></span>
              <span><span class="q-num"><?= $num ?>.</span> <?= h($it['label']) ?></span>
            </label>
            <?php if (!empty($it['hint'])): ?><p class="q-hint"><?= h($it['hint']) ?></p><?php endif; ?>
          </div>
        <?php endif; ?>
      <?php endforeach; ?>

      <!-- ─── 사진·영상 업로드 (탭별) ─── -->
      <section class="upload-block" aria-label="<?= h($sp['name']) ?> 사진·영상 업로드">
        <h3 class="q-section">📷 사진·영상 업로드 <span class="badge">선택</span></h3>
        <p class="q-hint"><strong>선택 사항입니다.</strong> 업로드 없이도 제출할 수 있어요. 이 탭에서 촬영·확인한 내용을 사진(jpg·png·webp·heic·gif) 또는 영상(mp4·mov·webm·m4v·3gp)으로 올릴 수 있어요. 한 파일당 최대 10MB.</p>

        <?php $mine = $uploads_by_spot[(int)$sp['id']] ?? []; ?>
        <?php if ($mine): ?>
          <ul class="upload-list">
            <?php foreach ($mine as $u):
              $url = h(upload_url($u));
              $orig = h($u['orig_name']);
              $sz  = upload_size_h((int)$u['size_bytes']);
            ?>
              <li class="upload-item">
                <?php if ($u['kind'] === 'image'): ?>
                  <a href="<?= $url ?>" target="_blank" rel="noopener" class="upload-thumb-link">
                    <img src="<?= $url ?>" alt="<?= $orig ?>" class="upload-thumb" loading="lazy">
                  </a>
                <?php else: ?>
                  <video class="upload-thumb" src="<?= $url ?>" controls preload="metadata" muted></video>
                <?php endif; ?>
                <div class="upload-meta">
                  <div class="upload-name"><?= $orig ?></div>
                  <div class="upload-sub"><?= h($u['kind'] === 'image' ? '사진' : '영상') ?> · <?= h($sz) ?> · <?= h($u['uploaded_at']) ?></div>
                </div>
                <form method="post" action="api/upload_delete.php" class="upload-del"
                      onsubmit="return confirm('이 파일을 삭제할까요? 되돌릴 수 없습니다.');">
                  <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                  <input type="hidden" name="upload_id" value="<?= (int)$u['id'] ?>">
                  <input type="hidden" name="spot_id" value="<?= (int)$sp['id'] ?>">
                  <button type="submit" class="btn-sm btn-danger" aria-label="<?= $orig ?> 삭제">삭제</button>
                </form>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php else: ?>
          <p class="empty">아직 올린 파일이 없습니다.</p>
        <?php endif; ?>

        <form method="post" action="api/upload.php" enctype="multipart/form-data" class="upload-form">
          <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
          <input type="hidden" name="spot_id" value="<?= (int)$sp['id'] ?>">
          <label class="upload-file">
            <span class="upload-file-label">파일 선택 (사진 또는 영상 · 선택 사항)</span>
            <!-- capture 속성 제거: 모바일에서 카메라 강제 실행 대신 OS 기본 선택창
                 (iOS: 사진 보관함/사진 촬영/파일 선택, Android: 갤러리/카메라/파일)을 띄운다 -->
            <input type="file" name="file"
                   accept="image/*,video/*"
                   aria-label="<?= h($sp['name']) ?> 탭에 올릴 사진 또는 영상 선택 (선택 사항)">
          </label>
          <button type="submit" class="btn-primary btn-sm">업로드</button>
        </form>
      </section>
    </section>
  <?php endforeach; ?>

  <!-- 우리 조 현황 -->
  <section class="team-status" aria-label="우리 조 제출 현황">
    <h2 class="panel-title">우리 조 현황</h2>
    <p class="submit-note">
      <strong><?= h($me['team_name']) ?></strong> 제출
      <strong id="teamSubCnt"><?= $teamSubCnt ?></strong>/<?= count($teamStat) ?>명
      · 완료 기준 <?= $teamThresh ?>명
      <?= $teamDone ? '<span class="badge ok">조 완료</span>' : '<span class="badge">진행중</span>' ?>
    </p>
    <ul class="team-members">
      <?php foreach ($teamStat as $ts): ?>
        <li class="team-member <?= $ts['submitted'] ? 'done' : '' ?>">
          <span class="tm-mark" aria-hidden="true"><?= $ts['submitted'] ? '✅' : '⬜' ?></span>
          <span class="tm-name"><?= h($ts['name']) ?><?= $ts['id'] === $memberId ? ' (나)' : '' ?></span>
          <span class="tm-state"><?= $ts['submitted'] ? '제출' : '미제출' ?></span>
        </li>
      <?php endforeach; ?>
    </ul>
    <p style="margin-top:10px"><a class="link" href="team.php">▸ 우리 조 조원별 진행상황 자세히 보기</a></p>
  </section>

  <!-- 제출 영역 -->
  <section class="submit-area" id="submitArea">
    <h2 class="panel-title">제출</h2>
    <fieldset class="q q-radio" id="surveyorField">
      <legend class="q-label">조사원 구분 <span class="req" aria-hidden="true">*</span></legend>
      <div class="opts">
        <?php foreach ($surveyorTypes as $st): ?>
          <label class="opt">
            <input type="radio" name="surveyor_type" value="<?= h($st) ?>"
                   <?= $curSurveyor === $st ? 'checked' : '' ?> <?= $dis ?>>
            <span class="opt-box" aria-hidden="true"></span>
            <span class="opt-text"><?= h($st) ?></span>
          </label>
        <?php endforeach; ?>
      </div>
    </fieldset>

    <!-- 시각장애 세부: 조사원 구분에서 '시각장애' 선택 시 표시 -->
    <fieldset class="q q-radio" id="visionField" <?= $curSurveyor === '시각장애' ? '' : 'hidden' ?>>
      <legend class="q-label">시각장애 세부 <span class="hint-inline">(선택)</span></legend>
      <div class="opts">
        <?php foreach (['전맹', '저시력'] as $vd): ?>
          <label class="opt">
            <input type="radio" name="vision_detail" value="<?= $vd ?>"
                   <?= $curVision === $vd ? 'checked' : '' ?> <?= $dis ?>>
            <span class="opt-box" aria-hidden="true"></span>
            <span class="opt-text"><?= $vd ?></span>
          </label>
        <?php endforeach; ?>
      </div>
    </fieldset>

    <!-- 휠체어 사용 여부 (항상 표시, 선택) -->
    <div class="q">
      <label class="chkline">
        <input type="checkbox" id="wheelchair" <?= (string)$curWheelchair === '1' ? 'checked' : '' ?> <?= $dis ?>>
        <span class="chk-box" aria-hidden="true"></span>
        <span>휠체어 사용 <span class="hint-inline">(사용하시면 체크)</span></span>
      </label>
    </div>

    <div class="q">
      <label class="q-label" for="siteName">관광지 <span class="req" aria-hidden="true">*</span></label>
      <select id="siteName" <?= $dis ?>>
        <option value="">— 관광지를 선택하세요 —</option>
        <?php foreach (site_list() as $sn): ?>
          <option value="<?= h($sn) ?>" <?= $curSite === $sn ? 'selected' : '' ?>><?= h($sn) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <?php if ($locked): ?>
      <p class="submit-note" id="submitHint">체크·서술 응답은 <strong>자동 저장</strong>됩니다. 조사원 구분·관광지를 바꿨다면 <strong>수정 저장</strong>을 눌러 반영하세요.</p>
      <button type="button" class="btn-primary" id="submitBtn" data-can-submit="1" data-mode="edit">수정 저장</button>
    <?php else: ?>
      <p class="submit-note" id="submitHint"><strong>한 개 탭만 완료해도</strong> 제출할 수 있습니다. 조사원 구분·관광지를 선택하면 제출 버튼이 켜집니다.</p>
      <button type="button" class="btn-primary" id="submitBtn" data-can-submit="<?= $canSubmit ? '1' : '0' ?>" data-mode="new" disabled>제출하기</button>
    <?php endif; ?>
  </section>
</main>

<?php include __DIR__ . '/_footer.php'; ?>
<script src="<?= asset_url('assets/app.js') ?>"></script>
</body>
</html>
