// ── 탭 전환 ──────────────────────────────────────────────
  document.querySelectorAll('.tab').forEach(function (tab) {
  tab.addEventListener('click', function () {
    document.querySelectorAll('.tab').forEach(function (t) {
      t.classList.remove('active');
      t.setAttribute('aria-selected', 'false');
    });
    document.querySelectorAll('.panel').forEach(function (p) {
      p.classList.remove('active');
      p.hidden = true;
    });
    tab.classList.add('active');
    tab.setAttribute('aria-selected', 'true');
    var panel = document.getElementById(tab.dataset.target);
    if (panel) { panel.classList.add('active'); panel.hidden = false; }
  });
});

// ── 토스트 ──────────────────────────────────────────────
function toast(msg) {
  var el = document.createElement('div');
  el.className = 'toast';
  el.textContent = msg;
  document.body.appendChild(el);
  setTimeout(function () { el.remove(); }, 4000);
}

var LOCKED = document.querySelector('.container') && document.querySelector('.container').dataset.locked === '1';
var submitBtn = document.getElementById('submitBtn');
// 한 탭만 완료해도 제출 가능 — 서버가 내려준 초기값
var canSubmit = !!(submitBtn && submitBtn.dataset.canSubmit === '1');

// ── 응답 저장 ────────────────────────────────────────────
function collect(itemId) {
  var value = '';
  var note = null;
  var radios = document.querySelectorAll('input[name="item_' + itemId + '"]');
  if (radios.length) {
    var checked = document.querySelector('input[name="item_' + itemId + '"]:checked');
    value = checked ? checked.value : '';
  }
  var chk = document.querySelector('input.item-check[data-item="' + itemId + '"]');
  if (chk) { value = chk.checked ? '1' : ''; }
  var ta = document.querySelector('textarea.ta[data-item="' + itemId + '"]');
  if (ta) { value = ta.value; }
  var nt = document.querySelector('textarea.note[data-item="' + itemId + '"]');
  if (nt) { note = nt.value; }
  return { value: value, note: note };
}

var lastRemain = null;

function applyProgress(p, canSub) {
  document.getElementById('progressFill').style.width = p.pct + '%';
  document.getElementById('progressPct').textContent = p.pct + '%';
  document.getElementById('progressDone').textContent = p.done;
  var rem = document.getElementById('progressRemain');
  if (rem) rem.textContent = p.remaining;
  // 스크린리더 안내: 남은 항목 수가 바뀔 때만 갱신
  if (p.remaining !== lastRemain) {
    var live = document.getElementById('remainLive');
    if (live) live.textContent = '남은 항목 ' + p.remaining + '개';
    lastRemain = p.remaining;
  }
  if (typeof canSub === 'boolean') canSubmit = canSub;
  refreshSubmitState();
}

function saveItem(itemId) {
  if (LOCKED) return;
  var data = collect(itemId);
  fetch('api/save.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ item_id: itemId, value: data.value, note: data.note })
  })
    .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
    .then(function (res) {
      if (!res.ok || !res.j.ok) {
        if (res.j.locked) { toast('이미 제출되어 수정할 수 없습니다.'); return; }
        throw new Error(res.j.error || '저장 오류');
      }
      applyProgress(res.j.progress, res.j.can_submit);
    })
    .catch(function (err) { toast('저장 실패: ' + err.message); });
}

// 라디오/체크박스: 즉시 저장
document.querySelectorAll('input[type=radio][data-item], input.item-check[data-item]').forEach(function (el) {
  el.addEventListener('change', function () { saveItem(parseInt(el.dataset.item, 10)); });
});
// 텍스트/개선사항: 입력 멈춘 뒤(디바운스) 저장
var timers = {};
document.querySelectorAll('textarea.ta[data-item], textarea.note[data-item]').forEach(function (el) {
  el.addEventListener('input', function () {
    var id = parseInt(el.dataset.item, 10);
    clearTimeout(timers[id]);
    timers[id] = setTimeout(function () { saveItem(id); }, 700);
  });
  el.addEventListener('blur', function () {
    var id = parseInt(el.dataset.item, 10);
    clearTimeout(timers[id]);
    saveItem(id);
  });
});

// ── 제출 폼 상태 ─────────────────────────────────────────
function surveyorValue() {
  var c = document.querySelector('input[name="surveyor_type"]:checked');
  return c ? c.value : '';
}
function siteValue() {
  var el = document.getElementById('siteName');
  return el ? el.value.trim() : '';
}
function visionValue() {
  var c = document.querySelector('input[name="vision_detail"]:checked');
  return c ? c.value : '';
}
function wheelchairChecked() {
  var el = document.getElementById('wheelchair');
  return el ? el.checked : false;
}
// 시각장애 선택 시에만 세부(전맹/저시력) 표시
function toggleVision() {
  var f = document.getElementById('visionField');
  if (!f) return;
  f.hidden = surveyorValue() !== '시각장애';
}
// 제출 버튼: 한 탭 완료(canSubmit) + 조사원 구분 + 관광지 선택 시 활성
function refreshSubmitState() {
  if (!submitBtn) return;
  submitBtn.disabled = !(canSubmit && surveyorValue() !== '' && siteValue() !== '');
}

var siteEl = document.getElementById('siteName');
if (siteEl) { siteEl.addEventListener('change', refreshSubmitState); siteEl.addEventListener('input', refreshSubmitState); }
document.querySelectorAll('input[name="surveyor_type"]').forEach(function (el) {
  el.addEventListener('change', function () { toggleVision(); refreshSubmitState(); });
});

// 초기 상태 반영
toggleVision();
refreshSubmitState();

// ── 제출 ────────────────────────────────────────────────
if (submitBtn) {
  var editMode = submitBtn.dataset.mode === 'edit';
  submitBtn.addEventListener('click', function () {
    if (submitBtn.disabled) return;
    var confirmMsg = editMode
      ? '변경한 조사원 구분·관광지를 저장할까요?'
      : '제출할까요? (제출 후에도 조사 기간 내에는 계속 수정할 수 있어요)';
    if (!confirm(confirmMsg)) return;
    submitBtn.disabled = true;
    fetch('api/submit.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        surveyor_type: surveyorValue(),
        site_name: siteValue(),
        wheelchair: wheelchairChecked(),
        vision_detail: surveyorValue() === '시각장애' ? visionValue() : ''
      })
    })
      .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
      .then(function (res) {
        if (!res.ok || !res.j.ok) { throw new Error(res.j.error || '제출 오류'); }
        if (res.j.notice) { toast(res.j.notice); }
        else { toast(res.j.edited ? '수정 사항을 저장했습니다.' : '제출되었습니다.'); }
        setTimeout(function () { location.reload(); }, 800);
      })
      .catch(function (err) { submitBtn.disabled = false; toast('실패: ' + err.message); });
  });
}