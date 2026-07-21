<?php
/** 관리자 대시보드 — 조(팀) 단위 완료 현황 (admin.php 안에서 include) */
$token          = csrf_token();
$threshold      = team_complete_threshold();
$totalMembers   = total_member_count();
$totalSubmitted = submitted_count();

$teams = db()->query('SELECT id, name FROM teams ORDER BY id')->fetchAll();
$comp = [];
foreach (db()->query('SELECT team_id, email_sent, email_error FROM completions') as $r) {
    $comp[(int)$r['team_id']] = $r;
}
$teamData = []; $completeTeams = 0;
foreach ($teams as $t) {
    $tid = (int)$t['id'];
    $sc  = team_submitted_count($tid);
    $mc  = team_member_count($tid);
    $done = $sc >= $threshold;
    if ($done) $completeTeams++;
    $teamData[] = ['id' => $tid, 'name' => $t['name'], 'mc' => $mc, 'sc' => $sc,
                   'done' => $done, 'comp' => $comp[$tid] ?? null,
                   'members' => team_members_status($tid)];
}
?>
<div class="toolbar">
  <h2 class="section-title" style="margin:0">제출 현황 (조 단위 완료 기준 <?= $threshold ?>명)</h2>
  <div class="toolbar-actions">
    <a class="btn-sm" href="admin.php?action=export&type=summary">⬇ 제출 요약 CSV</a>
    <a class="btn-sm" href="admin.php?action=export&type=detail">⬇ 응답 상세 CSV</a>
  </div>
</div>

<div class="stat-row">
  <div class="stat">
    <div class="stat-num"><?= $totalSubmitted ?> / <?= $totalMembers ?></div>
    <div class="stat-lbl">전체 제출 인원</div>
  </div>
  <div class="stat">
    <div class="stat-num"><?= $completeTeams ?> / <?= count($teams) ?></div>
    <div class="stat-lbl">완료된 조 (<?= $threshold ?>명↑)</div>
  </div>
  <div class="stat">
    <div class="stat-num"><?= $threshold ?>명</div>
    <div class="stat-lbl">조 완료 기준</div>
  </div>
</div>

<h2 class="section-title">🏁 조별 현황</h2>
<table class="admin-table">
  <thead>
    <tr><th scope="col">조</th><th scope="col">제출/인원</th><th scope="col">상태</th>
        <th scope="col">완료 메일</th><th scope="col"></th></tr>
  </thead>
  <tbody>
  <?php foreach ($teamData as $td): ?>
    <tr class="<?= $td['done'] ? 'row-done' : '' ?>">
      <th scope="row"><?= h($td['name']) ?></th>
      <td><strong><?= $td['sc'] ?></strong> / <?= $td['mc'] ?>명</td>
      <td><?= $td['done'] ? '<span class="badge ok">완료</span>' : '<span class="badge">진행중</span>' ?></td>
      <td>
        <?php if (!$td['comp']): ?>—
        <?php elseif ($td['comp']['email_sent']): ?><span class="badge ok">발송됨</span>
        <?php else: ?><span class="badge err" title="<?= h($td['comp']['email_error']) ?>">실패</span><?php endif; ?>
      </td>
      <td>
        <form method="post" class="inline-form" onsubmit="return confirm('<?= h($td['name']) ?> 완료 알림 메일을 지금 발송할까요?')">
          <input type="hidden" name="csrf" value="<?= h($token) ?>">
          <input type="hidden" name="action" value="force_send">
          <input type="hidden" name="team_id" value="<?= $td['id'] ?>">
          <button class="btn-sm" type="submit">✉ 발송</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$teamData): ?><tr><td colspan="5" class="empty">등록된 조가 없습니다.</td></tr><?php endif; ?>
  </tbody>
</table>
<p class="hint">각 조가 <?= $threshold ?>명 이상 제출하면 자동으로 완료 알림 메일이 발송됩니다. 필요하면 위 버튼으로 강제 발송할 수 있습니다.</p>

<h2 class="section-title">👥 조원별 제출 현황</h2>
<?php foreach ($teamData as $td): ?>
  <h3 class="q-section" style="color:#fff"><?= h($td['name']) ?> — <?= $td['sc'] ?>/<?= $td['mc'] ?>명 제출
    <?= $td['done'] ? '(완료)' : '' ?></h3>
  <table class="admin-table">
    <thead><tr><th scope="col">이름</th><th scope="col">제출</th><th scope="col"></th></tr></thead>
    <tbody>
    <?php foreach ($td['members'] as $m): ?>
      <tr class="<?= $m['submitted'] ? 'row-done' : '' ?>">
        <th scope="row"><?= h($m['name']) ?></th>
        <td><?= $m['submitted'] ? '<span class="badge ok">제출</span>' : '<span class="badge">미제출</span>' ?></td>
        <td><?php if ($m['submitted']): ?><a class="link" href="admin.php?view=member&mid=<?= (int)$m['id'] ?>">응답보기</a><?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$td['members']): ?><tr><td colspan="3" class="empty">조원이 없습니다.</td></tr><?php endif; ?>
    </tbody>
  </table>
<?php endforeach; ?>
