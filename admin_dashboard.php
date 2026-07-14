<?php
/** 관리자 대시보드 — 제출 현황 + 관광지별 커버 (admin.php 안에서 include) */
$token        = csrf_token();
$totalMembers = total_member_count();
$submitted    = submitted_count();
$reqTotal     = required_item_count();
$sitesDone    = sites_complete();
$completion   = db()->query('SELECT * FROM completions WHERE id = 1')->fetch();

$sites     = site_list();
$reqSites  = required_sites();
$siteCounts = site_submission_counts();
$coveredReq = 0;
foreach ($reqSites as $s) if (($siteCounts[$s] ?? 0) > 0) $coveredReq++;

$board = leaderboard();
$members = db()->query(
    'SELECT m.id, m.name, m.phone, t.name AS team,
            sub.submitted_at, sub.surveyor_type, sub.site_name
     FROM members m JOIN teams t ON t.id = m.team_id
     LEFT JOIN submissions sub ON sub.member_id = m.id
     ORDER BY t.id, m.id')->fetchAll();
?>
<div class="toolbar">
  <h2 class="section-title" style="margin:0">제출 현황</h2>
  <div class="toolbar-actions">
    <a class="btn-sm" href="admin.php?action=export&type=summary">⬇ 제출 요약 CSV</a>
    <a class="btn-sm" href="admin.php?action=export&type=detail">⬇ 응답 상세 CSV</a>
  </div>
</div>

<div class="stat-row">
  <div class="stat">
    <div class="stat-num"><?= $submitted ?> / <?= $totalMembers ?></div>
    <div class="stat-lbl">제출 완료 인원</div>
  </div>
  <div class="stat">
    <div class="stat-num"><?= $coveredReq ?> / <?= count($reqSites) ?></div>
    <div class="stat-lbl">필수 관광지(1~<?= count($reqSites) ?>) 커버</div>
  </div>
  <div class="stat">
    <div class="stat-num"><?= $sitesDone ? '<span class="badge ok">완료</span>' : '진행중' ?></div>
    <div class="stat-lbl">완료 조건 충족</div>
  </div>
  <div class="stat">
    <div class="stat-num">
      <?php if (!$completion): ?>—
      <?php elseif ($completion['email_sent']): ?><span class="badge ok">발송됨</span>
      <?php else: ?><span class="badge err" title="<?= h($completion['email_error']) ?>">실패</span><?php endif; ?>
    </div>
    <div class="stat-lbl">완료 알림 메일</div>
  </div>
</div>

<div class="toolbar" style="margin-top:8px">
  <div class="hint">관광지 1~<?= count($reqSites) ?>번이 모두 커버되면 자동 발송됩니다. 지금 강제로 보낼 수도 있습니다.</div>
  <form method="post" class="inline-form" onsubmit="return confirm('완료 알림 메일을 지금 발송할까요?')">
    <input type="hidden" name="csrf" value="<?= h($token) ?>">
    <input type="hidden" name="action" value="force_send">
    <button class="btn-sm" type="submit">✉ 완료 메일 강제 발송</button>
  </form>
</div>

<h2 class="section-title">🗺️ 관광지별 현황 (5개 체크리스트)</h2>
<table class="admin-table">
  <thead><tr><th scope="col">#</th><th scope="col">관광지</th><th scope="col">제출 인원</th><th scope="col">상태</th></tr></thead>
  <tbody>
  <?php foreach ($sites as $i => $s): $c = $siteCounts[$s] ?? 0; $isReq = in_array($s, $reqSites, true); ?>
    <tr class="<?= $c > 0 ? 'row-done' : '' ?>">
      <td><?= $i + 1 ?></td>
      <th scope="row"><?= h($s) ?> <?php if ($isReq): ?><span class="req" title="완료 필수">*</span><?php endif; ?></th>
      <td><?= $c ?>명</td>
      <td><?= $c > 0 ? '<span class="badge ok">완료</span>' : ($isReq ? '<span class="badge err">미완료</span>' : '<span class="badge">선택</span>') ?></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$sites): ?><tr><td colspan="4" class="empty">관광지 목록이 없습니다. (config.php의 SITE_LIST)</td></tr><?php endif; ?>
  </tbody>
</table>
<p class="hint"><span class="req">*</span> = 완료 필수 관광지 (1~<?= count($reqSites) ?>번). 이 관광지가 모두 ‘완료’면 메일이 발송됩니다.</p>

<h2 class="section-title">참가자별 제출</h2>
<table class="admin-table">
  <thead>
    <tr><th scope="col">조</th><th scope="col">이름</th><th scope="col">상태</th>
        <th scope="col">관광지</th><th scope="col">조사원 구분</th><th scope="col"></th></tr>
  </thead>
  <tbody>
  <?php foreach ($members as $m): $done = $m['submitted_at'] !== null; ?>
    <tr class="<?= $done ? 'row-done' : '' ?>">
      <td><?= h($m['team']) ?></td>
      <th scope="row"><?= h($m['name']) ?></th>
      <td><?= $done ? '<span class="badge ok">제출</span>' : '<span class="badge">미제출</span>' ?></td>
      <td><?= h($m['site_name'] ?: '—') ?></td>
      <td><?= h($m['surveyor_type'] ?: '—') ?></td>
      <td><a class="link" href="admin.php?view=member&mid=<?= (int)$m['id'] ?>">응답보기</a></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$members): ?><tr><td colspan="6" class="empty">등록된 참가자가 없습니다.</td></tr><?php endif; ?>
  </tbody>
</table>

<h2 class="section-title">🏆 조별 제출 순위</h2>
<ol class="leaderboard">
  <?php foreach ($board as $b): ?>
    <li class="lb-row">
      <span class="lb-rank rank-<?= $b['rank'] <= 3 ? $b['rank'] : 'x' ?>"><?= $b['rank'] ?></span>
      <span class="lb-name"><?= h($b['name']) ?></span>
      <span class="lb-bar"><span class="lb-fill" style="width:<?= $b['pct'] ?>%"></span></span>
      <span class="lb-pct"><?= $b['submitted'] ?>/<?= $b['members'] ?></span>
    </li>
  <?php endforeach; ?>
  <?php if (!$board): ?><li class="lb-row"><span class="empty">조가 없습니다.</span></li><?php endif; ?>
</ol>
