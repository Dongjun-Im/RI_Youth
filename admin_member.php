<?php
/** 참가자 응답 상세 뷰 — admin.php?view=member&mid=X */
$mid = (int)($_GET['mid'] ?? 0);
$m = null;
if ($mid) {
    $st = db()->prepare('SELECT m.*, t.name AS team FROM members m JOIN teams t ON t.id = m.team_id WHERE m.id = ?');
    $st->execute([$mid]);
    $m = $st->fetch() ?: null;
}
if (!$m) { echo '<p class="empty">참가자를 찾을 수 없습니다.</p><p><a class="link" href="admin.php">← 대시보드</a></p>'; return; }

$sub       = member_submission($mid);
$responses = member_responses($mid);
$prog      = member_progress($mid);
$spots = db()->query('SELECT * FROM spots ORDER BY sort_order, id')->fetchAll();
$itemsBySpot = [];
foreach (db()->query('SELECT * FROM items ORDER BY spot_id, sort_order, id') as $it) {
    $itemsBySpot[$it['spot_id']][] = $it;
}
?>
<p><a class="link" href="admin.php">← 대시보드</a></p>
<h2 class="section-title"><?= h($m['team']) ?> · <?= h($m['name']) ?> 응답 상세</h2>
<table class="admin-table"><tbody>
  <tr><th scope="row">제출 상태</th><td><?= $sub ? '<span class="badge ok">제출</span> ' . h(substr($sub['submitted_at'], 0, 16)) : '<span class="badge">미제출</span> (진행 ' . $prog['done'] . '/' . $prog['total'] . ')' ?></td></tr>
  <tr><th scope="row">조사원 구분</th><td><?= h($sub['surveyor_type'] ?? '—') ?></td></tr>
  <tr><th scope="row">관광지</th><td><?= h($sub['site_name'] ?? '—') ?></td></tr>
</tbody></table>

<?php foreach ($spots as $sp): $items = $itemsBySpot[$sp['id']] ?? []; ?>
  <h3 class="q-section"><?= h($sp['name']) ?></h3>
  <table class="admin-table">
    <thead><tr><th scope="col" style="width:50%">항목</th><th scope="col">응답</th><th scope="col">개선사항</th></tr></thead>
    <tbody>
    <?php $sec = null; foreach ($items as $it): $r = $responses[(int)$it['id']] ?? null;
        if (($it['section'] ?? '') !== $sec) { $sec = $it['section'] ?? '';
            if ($sec !== '') echo '<tr><td colspan="3" class="muted-cell"><b>' . h($sec) . '</b></td></tr>'; } ?>
      <tr>
        <td><?= h($it['label']) ?></td>
        <td><?= ($r && $r['value'] !== null && $r['value'] !== '') ? h($r['value']) : '<span class="muted-cell">—</span>' ?></td>
        <td class="muted-cell"><?= h($r['note'] ?? '') ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$items): ?><tr><td colspan="3" class="empty">항목 없음</td></tr><?php endif; ?>
    </tbody>
  </table>
<?php endforeach; ?>
