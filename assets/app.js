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

var LOCKED = document.querySelector('.container')?.dataset.locked === '1';

// ── 응답 저장 ────────────────────────────────────────────
// 한 항목의 현재 값(value)과 개선사항(note)을 DOM에서 수집
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

function applyProgress(p) {
  document.getElementById('progressFill').style.width = p.pct + '%';
  document.getElementById('progressPct').textContent = p.pct + '%';
  document.getElementById('progressDone').textContent = p.done;
  var rem = document.getElementById('progressRemain');
  if (rem) rem.textContent = p.remaining;
  var remInline = document.getElementById('remainInline');
  if (remInline) remInline.textContent = p.remaining;
  // 스크린리더 안내: 남은 항목 수가 바뀔 때만 갱신(과도한 낭독 방지)
  if (p.remaining !== lastRemain) {
    var live = document.getElementById('remainLive');
    if (live) live.textContent = '남은 항목 ' + p.remaining + '개';
    lastRemain = p.remaining;
  }
  refreshSubmitState(p);
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
      applyProgress(res.j.progress);
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

// ── 제출 ────────────────────────────────────────────────
function surveyorValue() {
  var c = document.querySelector('input[name="surveyor_type"]:checked');
  return c ? c.value : '';
}
function siteValue() {
  var el = document.getElementById('siteName');
  return el ? el.value.trim() : '';
}
function refreshSubmitState(p) {
  var btn = document.getElementById('submitBtn');
  if (!btn) return;
  var ready = p.remaining === 0 && surveyorValue() !== '' && siteValue() !== '';
  btn.disabled = !ready;
}

var siteEl = document.getElementById('siteName');
function siteChanged() { refreshSubmitState({ remaining: parseInt(document.getElementById('progressRemain').textContent, 10) }); }
if (siteEl) { siteEl.addEventListener('change', siteChanged); siteEl.addEventListener('input', siteChanged); }
document.querySelectorAll('input[name="surveyor_type"]').forEach(function (el) {
  el.addEventListener('change', function () { refreshSubmitState({ remaining: parseInt(document.getElementById('progressRemain').textContent, 10) }); });
});

var submitBtn = document.getElementById('submitBtn');
if (submitBtn) {
  submitBtn.addEventListener('click', function () {
    if (submitBtn.disabled) return;
    if (!confirm('제출하면 응답을 수정할 수 없습니다. 제출할까요?')) return;
    submitBtn.disabled = true;
    fetch('api/submit.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ surveyor_type: surveyorValue(), site_name: siteValue() })
    })
      .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
      .then(function (res) {
        if (!res.ok || !res.j.ok) { throw new Error(res.j.error || '제출 오류'); }
        if (res.j.notice) { toast(res.j.notice); }
        setTimeout(function () { location.reload(); }, 800);
      })
      .catch(function (err) { submitBtn.disabled = false; toast('제출 실패: ' + err.message); });
  });
}
