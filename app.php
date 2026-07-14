<?php
require_once __DIR__ . '/lib.php';
$me = require_member();
$memberId = (int)$me['id'];

$spots = db()->query('SELECT * FROM spots ORDER BY sort_order, id')->fetchAll();
$itemsBySpot = [];
foreach (db()->query('SELECT * FROM items ORDER BY spot_id, sort_order, id') as $it) {
    $itemsBySpot[$it['spot_id']][] = $it;
}
$responses  = member_responses($memberId);
$prog       = member_progress($memberId);
$submission = member_submission($memberId);
$locked     = $submission !== null;
$dis        = $locked ? 'disabled' : '';

$surveyorTypes = ['지체·뇌병변장애', '시각장애', '청각장애', '지적·자폐성장애', '기타', '비장애'];
$curSurveyor   = $submission['surveyor_type'] ?? '';
$curSite       = $submission['site_name'] ?? '';
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= h(APP_TITLE) ?></title>
<link rel="stylesheet" href="assets/style.css">
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

<main class="container" data-locked="<?= $locked ? '1' : '0' ?>">
  <div class="banner-done" id="doneBanner" <?= $locked ? '' : 'hidden' ?>>
    ✅ 제출이 완료되었습니다. 응답은 잠금되어 수정할 수 없습니다.
    <?php if ($curSite): ?><br><small>관광지: <?= h($curSite) ?><?= $curSurveyor ? ' · 조사원 구분: ' . h($curSurveyor) : '' ?></small><?php endif; ?>
  </div>

  <!-- 대분류 탭 -->
  <div class="tabs" role="tablist" aria-label="조사 항목 분류">
    <?php foreach ($spots as $i => $sp): ?>
      <button class="tab<?= $i === 0 ? ' active' : '' ?>" role="tab"
              aria-selected="<?= $i === 0 ? 'true' : 'false' ?>"
              aria-controls="panel-<?= (int)$sp['id'] ?>" id="tab-<?= (int)$sp['id'] ?>"
              data-target="panel-<?= (int)$sp['id'] ?>"><?= h($sp['name']) ?></button>
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
    </section>
  <?php endforeach; ?>

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
      <p class="submit-note ok">이미 제출되었습니다. 감사합니다!</p>
    <?php else: ?>
      <p class="submit-note" id="submitHint">모든 필수 항목(<span id="remainInline"><?= $prog['remaining'] ?></span>개 남음)을 완료하고 조사원 구분·관광지명을 입력하면 제출할 수 있습니다.</p>
      <button type="button" class="btn-primary" id="submitBtn" disabled>제출하기</button>
    <?php endif; ?>
  </section>
</main>

<script src="assets/app.js"></script>
</body>
</html>
