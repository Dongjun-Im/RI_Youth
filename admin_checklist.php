<?php
/** 체크리스트(대분류 탭 · 항목) 관리 뷰 (admin.php 안에서 include). */
$spots     = list_spots();
$token     = csrf_token();
$curSpot   = (int)($_GET['spot'] ?? ($spots[0]['id'] ?? 0));
$editItem  = (int)($_GET['edititem'] ?? 0);
$editSpot  = (int)($_GET['editspot'] ?? 0);

$itemCounts = [];
foreach (db()->query('SELECT spot_id, COUNT(*) c FROM items GROUP BY spot_id') as $r) {
    $itemCounts[(int)$r['spot_id']] = (int)$r['c'];
}

/** 항목 입력 폼 필드(추가/수정 공용) 출력 */
function item_form_fields(array $it = []): void {
    $types = ['radio' => '선택(라디오)', 'check' => '체크', 'text' => '서술(텍스트)'];
    $type = $it['type'] ?? 'radio';
    ?>
    <div class="grid-form">
      <label>소제목(섹션)
        <input name="section" value="<?= h($it['section'] ?? '') ?>" placeholder="예: Ⅰ 주차장 접근성">
      </label>
      <label>유형
        <select name="type">
          <?php foreach ($types as $v => $lbl): ?>
            <option value="<?= $v ?>" <?= $type === $v ? 'selected' : '' ?>><?= $lbl ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="col-2">항목(문항) 내용
        <input name="label" value="<?= h($it['label'] ?? '') ?>" required placeholder="문항 또는 확인 포인트">
      </label>
      <label class="col-2">선택지 (라디오일 때, | 로 구분)
        <input name="options" value="<?= h($it['options'] ?? '') ?>" placeholder="적합|애매|미흡">
      </label>
      <label class="col-2">도움말(근거·기준)
        <input name="hint" value="<?= h($it['hint'] ?? '') ?>" placeholder="예: 폭 3.3m↑, 길이 5.0m↑">
      </label>
      <label>정렬 순서
        <input name="sort_order" type="number" value="<?= (int)($it['sort_order'] ?? 0) ?>">
      </label>
      <label class="chk-inline"><input type="checkbox" name="required" value="1" <?= !isset($it['required']) || (int)$it['required'] === 1 ? 'checked' : '' ?>> 필수</label>
      <label class="chk-inline"><input type="checkbox" name="has_note" value="1" <?= isset($it['has_note']) && (int)$it['has_note'] === 1 ? 'checked' : '' ?>> 개선사항란</label>
    </div>
    <?php
}
?>
<h2 class="section-title">대분류(탭) 관리</h2>
<form method="post" class="form-inline">
  <input type="hidden" name="csrf" value="<?= h($token) ?>">
  <input type="hidden" name="action" value="spot_add">
  <input name="name" placeholder="대분류 이름 (예: 이동권)" required>
  <input name="sort_order" type="number" value="<?= count($spots) + 1 ?>" title="정렬 순서" style="width:70px">
  <button class="btn-sm" type="submit">탭 추가</button>
</form>
<table class="admin-table">
  <thead><tr><th scope="col">순서</th><th scope="col">대분류(탭)</th><th scope="col">항목 수</th><th scope="col"></th></tr></thead>
  <tbody>
  <?php foreach ($spots as $s): $sid = (int)$s['id']; ?>
    <?php if ($editSpot === $sid): ?>
      <tr><td colspan="4">
        <form method="post" class="form-inline">
          <input type="hidden" name="csrf" value="<?= h($token) ?>">
          <input type="hidden" name="action" value="spot_update">
          <input type="hidden" name="id" value="<?= $sid ?>">
          <input name="sort_order" type="number" value="<?= (int)$s['sort_order'] ?>" style="width:70px">
          <input name="name" value="<?= h($s['name']) ?>" required>
          <button class="btn-primary btn-sm" type="submit">저장</button>
          <a class="link" href="admin.php?view=checklist&spot=<?= $curSpot ?>">취소</a>
        </form>
      </td></tr>
    <?php else: ?>
      <tr class="<?= $sid === $curSpot ? 'row-sel' : '' ?>">
        <td><?= (int)$s['sort_order'] ?></td>
        <td><?= h($s['name']) ?></td>
        <td><?= (int)($itemCounts[$sid] ?? 0) ?>개</td>
        <td class="actions">
          <a class="link" href="admin.php?view=checklist&spot=<?= $sid ?>">항목관리</a>
          <a class="link" href="admin.php?view=checklist&spot=<?= $curSpot ?>&editspot=<?= $sid ?>">수정</a>
          <form method="post" class="inline-form" onsubmit="return confirm('이 탭과 소속 항목·응답이 모두 삭제됩니다. 계속할까요?')">
            <input type="hidden" name="csrf" value="<?= h($token) ?>">
            <input type="hidden" name="action" value="spot_delete">
            <input type="hidden" name="id" value="<?= $sid ?>">
            <button class="linkbtn danger" type="submit">삭제</button>
          </form>
        </td>
      </tr>
    <?php endif; ?>
  <?php endforeach; ?>
  <?php if (!$spots): ?><tr><td colspan="4" class="empty">탭이 없습니다. 위에서 추가하세요.</td></tr><?php endif; ?>
  </tbody>
</table>

<?php if ($curSpot && $spots):
    $curName = '';
    foreach ($spots as $s) { if ((int)$s['id'] === $curSpot) { $curName = $s['name']; break; } }
    $items = list_items_by_spot($curSpot); ?>
  <h2 class="section-title">“<?= h($curName) ?>” 항목 추가</h2>
  <form method="post" class="card-form">
    <input type="hidden" name="csrf" value="<?= h($token) ?>">
    <input type="hidden" name="action" value="item_add">
    <input type="hidden" name="spot_id" value="<?= $curSpot ?>">
    <?php item_form_fields(['sort_order' => count($items) + 1]); ?>
    <button class="btn-primary btn-sm" type="submit">항목 추가</button>
  </form>

  <h2 class="section-title">항목 목록 (<?= count($items) ?>개)</h2>
  <table class="admin-table">
    <thead><tr><th scope="col">순서</th><th scope="col">섹션</th><th scope="col">항목</th><th scope="col">유형</th><th scope="col"></th></tr></thead>
    <tbody>
    <?php foreach ($items as $it): $iid = (int)$it['id']; ?>
      <?php if ($editItem === $iid): ?>
        <tr><td colspan="5">
          <form method="post" class="card-form">
            <input type="hidden" name="csrf" value="<?= h($token) ?>">
            <input type="hidden" name="action" value="item_update">
            <input type="hidden" name="id" value="<?= $iid ?>">
            <input type="hidden" name="spot_id" value="<?= $curSpot ?>">
            <?php item_form_fields($it); ?>
            <button class="btn-primary btn-sm" type="submit">저장</button>
            <a class="link" href="admin.php?view=checklist&spot=<?= $curSpot ?>">취소</a>
          </form>
        </td></tr>
      <?php else: ?>
        <tr>
          <td><?= (int)$it['sort_order'] ?></td>
          <td class="muted-cell"><?= h($it['section'] ?: '—') ?></td>
          <td>
            <?= h($it['label']) ?>
            <?php if (!(int)$it['required']): ?><span class="badge">선택</span><?php endif; ?>
            <?php if ((int)$it['has_note']): ?><span class="badge">개선사항</span><?php endif; ?>
            <?php if (!empty($it['options'])): ?><br><small class="muted-cell"><?= h($it['options']) ?></small><?php endif; ?>
          </td>
          <td><span class="badge"><?= h(item_type_label($it['type'])) ?></span></td>
          <td class="actions">
            <a class="link" href="admin.php?view=checklist&spot=<?= $curSpot ?>&edititem=<?= $iid ?>">수정</a>
            <form method="post" class="inline-form" onsubmit="return confirm('이 항목을 삭제할까요? (참가자 응답도 사라집니다)')">
              <input type="hidden" name="csrf" value="<?= h($token) ?>">
              <input type="hidden" name="action" value="item_delete">
              <input type="hidden" name="id" value="<?= $iid ?>">
              <input type="hidden" name="spot_id" value="<?= $curSpot ?>">
              <button class="linkbtn danger" type="submit">삭제</button>
            </form>
          </td>
        </tr>
      <?php endif; ?>
    <?php endforeach; ?>
    <?php if (!$items): ?><tr><td colspan="5" class="empty">등록된 항목이 없습니다. 위에서 추가하세요.</td></tr><?php endif; ?>
    </tbody>
  </table>
<?php endif; ?>
