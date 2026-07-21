<?php
/** 참가자용 — 우리 조 조원별·탭별 진행상황 (본인 조에 한함) */
require_once __DIR__ . '/lib.php';
no_store_headers();
if (!participant_access_open()) { render_access_closed(); }
$me = require_member();
$teamId    = (int)$me['team_id'];
$matrix    = team_progress_matrix($teamId);
$threshold = team_complete_threshold();
$subCnt = 0; foreach ($matrix['members'] as $r) if ($r['submitted']) $subCnt++;
$teamDone = $subCnt >= $threshold;
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= h(APP_TITLE) ?> — 우리 조 진행상황</title>
<link rel="stylesheet" href="<?= asset_url('assets/style.css') ?>">
</head>
<body>
<header class="topbar">
  <div class="topbar-inner">
    <div>
      <div class="brand"><?= h(APP_TITLE) ?></div>
      <div class="who"><?= h($me['team_name']) ?> 진행상황 · <?= h($me['name']) ?>님</div>
    </div>
    <a class="logout" href="app.php">← 돌아가기</a>
  </div>
</header>

<main class="container">
  <h2 class="panel-title"><?= h($me['team_name']) ?> 조원별 진행상황</h2>
  <p class="submit-note">
    제출 <strong><?= $subCnt ?></strong>/<?= count($matrix['members']) ?>명 · 완료 기준 <?= $threshold ?>명
    <?= $teamDone ? '<span class="badge ok">조 완료</span>' : '<span class="badge">진행중</span>' ?>
  </p>

  <div class="table-scroll">
    <table class="admin-table team-matrix">
      <thead>
        <tr>
          <th scope="col">이름</th>
          <?php foreach ($matrix['spots'] as $s): ?>
            <th scope="col"><?= h($s['name']) ?></th>
          <?php endforeach; ?>
          <th scope="col">합계</th>
          <th scope="col">제출</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($matrix['members'] as $r): $mine = $r['id'] === (int)$me['id']; ?>
        <tr class="<?= $mine ? 'row-sel' : '' ?>">
          <th scope="row"><?= h($r['name']) ?><?= $mine ? ' (나)' : '' ?></th>
          <?php foreach ($matrix['spots'] as $s): $c = $r['cells'][(int)$s['id']]; ?>
            <td class="<?= ($c['total'] > 0 && $c['complete']) ? 'cell-done' : '' ?>">
              <?php if ($c['total'] === 0): ?><span class="muted-cell">–</span>
              <?php else: ?><?= $c['done'] ?>/<?= $c['total'] ?><?= $c['complete'] ? ' ✓' : '' ?><?php endif; ?>
            </td>
          <?php endforeach; ?>
          <td><strong><?= $r['done'] ?></strong>/<?= $r['total'] ?></td>
          <td><?= $r['submitted'] ? '<span class="badge ok">제출</span>' : '<span class="badge">미제출</span>' ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$matrix['members']): ?><tr><td colspan="9" class="empty">조원이 없습니다.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
  <p class="hint">각 칸은 그 탭에서 <strong>완료한 필수 항목 수 / 전체 필수 항목 수</strong> 입니다. ✓ 는 그 탭을 모두 완료했다는 표시예요.</p>
  <p style="margin-top:14px"><a class="btn-sm" href="app.php">← 내 조사로 돌아가기</a></p>
</main>

<?php include __DIR__ . '/_footer.php'; ?>
</body>
</html>
