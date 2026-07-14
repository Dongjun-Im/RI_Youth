<?php
/** 참가자·조 관리 뷰 (admin.php 안에서 include). */
$teams   = list_teams();
$members = list_members();
$token   = csrf_token();
$editId  = (int)($_GET['edit'] ?? 0);       // 수정 중인 참가자
$editTeam = (int)($_GET['editteam'] ?? 0);  // 이름변경 중인 조

// 조별 인원 수
$counts = [];
foreach ($members as $m) { $counts[(int)$m['team_id']] = ($counts[(int)$m['team_id']] ?? 0) + 1; }
?>
<h2 class="section-title">참가자 등록</h2>
<?php if (!$teams): ?>
  <p class="hint">먼저 아래 ‘조 관리’에서 조를 하나 이상 추가하세요.</p>
<?php else: ?>
<form method="post" class="form-inline">
  <input type="hidden" name="csrf" value="<?= h($token) ?>">
  <input type="hidden" name="action" value="member_add">
  <select name="team_id" required>
    <?php foreach ($teams as $t): ?>
      <option value="<?= (int)$t['id'] ?>"><?= h($t['name']) ?></option>
    <?php endforeach; ?>
  </select>
  <input name="name" placeholder="이름" required>
  <input name="phone" type="tel" inputmode="numeric" placeholder="휴대폰번호 (010-1234-5678)" required>
  <button class="btn-primary btn-sm" type="submit">등록</button>
</form>
<?php endif; ?>

<h2 class="section-title">CSV 일괄 등록</h2>
<form method="post" enctype="multipart/form-data" class="form-inline">
  <input type="hidden" name="csrf" value="<?= h($token) ?>">
  <input type="hidden" name="action" value="members_import">
  <input type="file" name="csv" accept=".csv,text/csv" required>
  <button class="btn-sm" type="submit">업로드</button>
  <span class="hint">형식: <code>조,이름,휴대폰</code> (첫 줄 헤더 가능 · 없는 조는 자동 생성 · 하이픈 무관)</span>
</form>

<h2 class="section-title">참가자 목록 (<?= count($members) ?>명)</h2>
<table class="admin-table">
  <thead><tr><th scope="col">조</th><th scope="col">이름</th><th scope="col">휴대폰</th><th scope="col"></th></tr></thead>
  <tbody>
  <?php foreach ($members as $m): $mid = (int)$m['id']; ?>
    <?php if ($editId === $mid): ?>
      <tr>
        <td colspan="4">
          <form method="post" class="form-inline">
            <input type="hidden" name="csrf" value="<?= h($token) ?>">
            <input type="hidden" name="action" value="member_update">
            <input type="hidden" name="id" value="<?= $mid ?>">
            <select name="team_id" required>
              <?php foreach ($teams as $t): ?>
                <option value="<?= (int)$t['id'] ?>" <?= (int)$t['id'] === (int)$m['team_id'] ? 'selected' : '' ?>><?= h($t['name']) ?></option>
              <?php endforeach; ?>
            </select>
            <input name="name" value="<?= h($m['name']) ?>" required>
            <input name="phone" value="<?= h($m['phone']) ?>" required>
            <button class="btn-primary btn-sm" type="submit">저장</button>
            <a class="link" href="admin.php?view=members">취소</a>
          </form>
        </td>
      </tr>
    <?php else: ?>
      <tr>
        <td><?= h($m['team_name']) ?></td>
        <td><?= h($m['name']) ?></td>
        <td><?= h($m['phone']) ?></td>
        <td class="actions">
          <a class="link" href="admin.php?view=members&edit=<?= $mid ?>">수정</a>
          <form method="post" class="inline-form" onsubmit="return confirm('이 참가자를 삭제할까요?')">
            <input type="hidden" name="csrf" value="<?= h($token) ?>">
            <input type="hidden" name="action" value="member_delete">
            <input type="hidden" name="id" value="<?= $mid ?>">
            <button class="linkbtn danger" type="submit">삭제</button>
          </form>
        </td>
      </tr>
    <?php endif; ?>
  <?php endforeach; ?>
  <?php if (!$members): ?><tr><td colspan="4" class="empty">등록된 참가자가 없습니다.</td></tr><?php endif; ?>
  </tbody>
</table>

<h2 class="section-title">조 관리</h2>
<form method="post" class="form-inline">
  <input type="hidden" name="csrf" value="<?= h($token) ?>">
  <input type="hidden" name="action" value="team_add">
  <input name="name" placeholder="새 조 이름 (예: 6조)" required>
  <button class="btn-sm" type="submit">조 추가</button>
</form>
<table class="admin-table">
  <thead><tr><th scope="col">조</th><th scope="col">인원</th><th scope="col"></th></tr></thead>
  <tbody>
  <?php foreach ($teams as $t): $tid = (int)$t['id']; ?>
    <?php if ($editTeam === $tid): ?>
      <tr>
        <td colspan="3">
          <form method="post" class="form-inline">
            <input type="hidden" name="csrf" value="<?= h($token) ?>">
            <input type="hidden" name="action" value="team_rename">
            <input type="hidden" name="id" value="<?= $tid ?>">
            <input name="name" value="<?= h($t['name']) ?>" required>
            <button class="btn-primary btn-sm" type="submit">저장</button>
            <a class="link" href="admin.php?view=members">취소</a>
          </form>
        </td>
      </tr>
    <?php else: ?>
      <tr>
        <td><?= h($t['name']) ?></td>
        <td><?= (int)($counts[$tid] ?? 0) ?>명</td>
        <td class="actions">
          <a class="link" href="admin.php?view=members&editteam=<?= $tid ?>">이름변경</a>
          <form method="post" class="inline-form" onsubmit="return confirm('이 조와 소속 참가자·체크가 모두 삭제됩니다. 계속할까요?')">
            <input type="hidden" name="csrf" value="<?= h($token) ?>">
            <input type="hidden" name="action" value="team_delete">
            <input type="hidden" name="id" value="<?= $tid ?>">
            <button class="linkbtn danger" type="submit">삭제</button>
          </form>
        </td>
      </tr>
    <?php endif; ?>
  <?php endforeach; ?>
  <?php if (!$teams): ?><tr><td colspan="3" class="empty">조가 없습니다.</td></tr><?php endif; ?>
  </tbody>
</table>
