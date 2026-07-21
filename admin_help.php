<?php
/**
 * 관리자 도움말 — 각 기능 사용법을 섹션별로 설명.
 * 스크린리더로도 목차 → 본문을 순서대로 훑을 수 있도록 <nav> + <section> + <h2>/<h3> 구조 사용.
 */
?>
<section class="help-page" aria-labelledby="help-title">
  <h1 id="help-title" class="panel-title">❓ 관리자 도움말</h1>
  <p class="hint">이 페이지는 관리자 각 기능의 사용 방법을 순서대로 설명합니다. 목차에서 원하는 항목을 골라 이동하실 수 있어요.</p>

  <!-- 목차 (Table of Contents) -->
  <nav class="help-toc" aria-label="도움말 목차">
    <h2 class="help-toc-title">목차</h2>
    <ol class="help-toc-list">
      <li><a href="#help-1">1. 대시보드 보는 법</a></li>
      <li><a href="#help-2">2. 참가자 등록·수정·삭제</a></li>
      <li><a href="#help-3">3. 조(팀) 관리</a></li>
      <li><a href="#help-4">4. 참가자 CSV 대량 등록</a></li>
      <li><a href="#help-5">5. 체크리스트(문항) 관리</a></li>
      <li><a href="#help-6">6. 발신 이메일 설정</a></li>
      <li><a href="#help-7">7. 완료 알림 메일</a></li>
      <li><a href="#help-8">8. 관리자 비밀번호 변경</a></li>
      <li><a href="#help-9">9. 참가자별 상세 보기·CSV 내보내기</a></li>
      <li><a href="#help-10">10. 자주 묻는 질문 (FAQ)</a></li>
    </ol>
  </nav>

  <!-- 1. 대시보드 -->
  <section class="help-section" id="help-1" aria-labelledby="help-1-t">
    <h2 id="help-1-t" class="help-section-title">1. 대시보드 보는 법</h2>
    <p>대시보드는 <strong>전체 조사 진행 상황</strong>을 한눈에 보여주는 관리자 첫 화면입니다.</p>
    <h3>주요 정보</h3>
    <ul>
      <li><strong>참가자 수 / 제출 수</strong>: 등록된 참가자 대비 제출을 완료한 인원.</li>
      <li><strong>진행률 (%)</strong>: 참가자가 필수 항목을 얼마나 답변했는지의 평균.</li>
      <li><strong>필수 관광지(1~4번) 커버 여부</strong>: 조사 완료 조건. 관광지 1~4번이 모두 커버되면 완료 알림 메일이 자동 발송됩니다.</li>
      <li><strong>참가자별 표</strong>: 조·이름·휴대폰·제출 상태·응답 수. 이름을 클릭하면 상세 화면으로 이동.</li>
    </ul>
    <p class="hint">📌 대시보드는 <strong>30초마다 자동으로 새로고침</strong> 됩니다. 실시간 모니터링에 편리합니다.</p>
  </section>

  <!-- 2. 참가자 등록·수정·삭제 -->
  <section class="help-section" id="help-2" aria-labelledby="help-2-t">
    <h2 id="help-2-t" class="help-section-title">2. 참가자 등록·수정·삭제</h2>
    <p>좌측 메뉴 <strong>“👥 참가자 관리”</strong> 에서 진행합니다.</p>
    <h3>새 참가자 등록</h3>
    <ol>
      <li>페이지 상단 <strong>“새 참가자 등록”</strong> 폼에서 <strong>조·이름·휴대폰번호</strong> 입력.</li>
      <li>휴대폰번호는 하이픈(<code>-</code>) 있이/없이 모두 허용. 저장 시 자동으로 숫자만 남김.</li>
      <li><strong>“등록”</strong> 버튼 클릭.</li>
    </ol>
    <h3>기존 참가자 수정·삭제</h3>
    <ol>
      <li>참가자 목록에서 해당 행의 값 (조/이름/휴대폰) 을 직접 수정 후 <strong>“저장”</strong>.</li>
      <li>삭제하려면 해당 행의 <strong>“삭제”</strong> 버튼 클릭 → 확인 팝업에서 <strong>“확인”</strong>.</li>
    </ol>
    <p class="warn">⚠️ 참가자를 삭제하면 그 참가자의 <strong>모든 응답·업로드·제출 기록도 함께 삭제</strong>됩니다. 되돌릴 수 없으니 신중히 진행하세요.</p>
  </section>

  <!-- 3. 조 관리 -->
  <section class="help-section" id="help-3" aria-labelledby="help-3-t">
    <h2 id="help-3-t" class="help-section-title">3. 조(팀) 관리</h2>
    <p>참가자 관리 페이지 하단에 <strong>“조 관리”</strong> 섹션이 있습니다.</p>
    <ul>
      <li><strong>새 조 추가</strong>: 이름 입력 후 <strong>“조 추가”</strong> 버튼.</li>
      <li><strong>조 이름 변경</strong>: 해당 조의 이름을 직접 수정 후 <strong>“저장”</strong>.</li>
      <li><strong>조 삭제</strong>: 해당 조의 <strong>“삭제”</strong> 버튼. ⚠️ 조를 삭제하면 그 조의 <strong>모든 참가자도 함께 삭제</strong>됩니다.</li>
    </ul>
  </section>

  <!-- 4. CSV 대량 등록 -->
  <section class="help-section" id="help-4" aria-labelledby="help-4-t">
    <h2 id="help-4-t" class="help-section-title">4. 참가자 CSV 대량 등록</h2>
    <p>참가자가 여러 명일 때 엑셀·구글시트로 만든 CSV 파일을 한 번에 올릴 수 있습니다.</p>
    <h3>CSV 파일 형식</h3>
    <p>첫 줄은 헤더, 다음 줄부터 데이터. 열 순서: <strong>조 이름, 참가자 이름, 휴대폰번호</strong>.</p>
    <pre class="help-code">team,name,phone
1조,홍길동,010-1111-0001
1조,김철수,010-1111-0002
2조,이영희,010-2222-0001</pre>
    <h3>업로드 순서</h3>
    <ol>
      <li>참가자 관리 페이지의 <strong>“CSV 파일 대량 등록”</strong> 섹션.</li>
      <li><strong>“파일 선택”</strong> → CSV 선택 → <strong>“업로드”</strong>.</li>
      <li>기존에 없는 조는 자동 생성. 중복된 휴대폰번호는 건너뜀.</li>
    </ol>
    <p class="hint">💡 엑셀에서 저장할 때 <strong>“CSV UTF-8 (쉼표로 분리)”</strong> 형식을 선택해야 한글이 깨지지 않습니다.</p>
  </section>

  <!-- 5. 체크리스트 관리 -->
  <section class="help-section" id="help-5" aria-labelledby="help-5-t">
    <h2 id="help-5-t" class="help-section-title">5. 체크리스트(문항) 관리</h2>
    <p>좌측 메뉴 <strong>“🗂️ 체크리스트 관리”</strong>. 대분류 탭과 그 안의 개별 문항을 관리합니다.</p>
    <h3>대분류(탭) 추가·수정·삭제</h3>
    <ul>
      <li>탭 이름 (예: <strong>설문조사·이동권·시설편의·정보접근권·문화향유권</strong>).</li>
      <li>정렬 순서를 조정해 참가자 페이지의 탭 순서를 바꿀 수 있음.</li>
      <li>탭을 삭제하면 <strong>그 탭 안의 모든 문항·응답이 함께 삭제</strong>됩니다.</li>
    </ul>
    <h3>문항 추가</h3>
    <ol>
      <li>탭 아래의 <strong>“새 항목 추가”</strong> 폼에 입력:
        <ul>
          <li><strong>구분(section)</strong>: 소제목 (예: “Ⅰ 주차장 접근성”). 같은 소제목의 문항은 번호가 이어짐.</li>
          <li><strong>문항(label)</strong>: 화면에 표시될 질문·확인 포인트.</li>
          <li><strong>도움말(hint)</strong>: 근거·기준 등 참가자에게 보여줄 부연 설명 (선택).</li>
          <li><strong>유형(type)</strong>:
            <ul>
              <li><strong>radio</strong>: 선택지 중 하나 (선택지는 파이프 <code>|</code> 로 구분. 예: <code>적합|애매|미흡</code>).</li>
              <li><strong>check</strong>: 확인만 하는 체크박스.</li>
              <li><strong>text</strong>: 자유 서술.</li>
            </ul>
          </li>
          <li><strong>개선사항(has_note)</strong>: 응답 뒤에 서술란 표시 여부.</li>
          <li><strong>필수(required)</strong>: 체크 시 참가자가 반드시 답해야 제출 가능.</li>
        </ul>
      </li>
      <li><strong>“추가”</strong> 버튼 클릭.</li>
    </ol>
    <p class="hint">💡 정렬 순서는 숫자가 작을수록 위에 표시됩니다. 10, 20, 30 처럼 간격을 두면 나중에 사이에 끼워넣기 편합니다.</p>
  </section>

  <!-- 6. 발신 이메일 설정 -->
  <section class="help-section" id="help-6" aria-labelledby="help-6-t">
    <h2 id="help-6-t" class="help-section-title">6. 발신 이메일 설정</h2>
    <p>좌측 메뉴 <strong>“⚙️ 발신 설정”</strong>. 완료 알림 메일을 보내는 SMTP 계정을 설정합니다.</p>
    <h3>Gmail 사용 시 (권장)</h3>
    <ol>
      <li>구글 계정 로그인 → 2단계 인증 활성화 (필수).</li>
      <li><a href="https://myaccount.google.com/apppasswords" target="_blank" rel="noopener">Google 앱 비밀번호 발급 페이지</a> 에서 앱 비밀번호 16자리 발급.</li>
      <li>발신 설정 페이지에 입력:
        <ul>
          <li><strong>SMTP 호스트</strong>: <code>smtp.gmail.com</code></li>
          <li><strong>포트</strong>: <code>465</code> (SSL) 또는 <code>587</code> (STARTTLS)</li>
          <li><strong>SMTP 사용자</strong>: 본인 Gmail 주소</li>
          <li><strong>SMTP 비밀번호</strong>: 위에서 발급한 앱 비밀번호 16자리 (공백 포함 그대로)</li>
          <li><strong>발신자 주소·이름</strong>: 수신자에게 보일 정보</li>
          <li><strong>수신자 (관리자 이메일)</strong>: 완료 알림을 받을 주소</li>
        </ul>
      </li>
      <li><strong>“저장”</strong> 클릭.</li>
      <li><strong>“테스트 발송”</strong> 버튼으로 실제 발송 확인.</li>
    </ol>
    <p class="warn">⚠️ 일반 Gmail 비밀번호로는 안 됩니다. 반드시 <strong>앱 비밀번호</strong> 를 발급받아 사용하세요.</p>
  </section>

  <!-- 7. 완료 알림 메일 -->
  <section class="help-section" id="help-7" aria-labelledby="help-7-t">
    <h2 id="help-7-t" class="help-section-title">7. 완료 알림 메일</h2>
    <p>참가자들이 <strong>필수 관광지(1~4번)</strong> 를 모두 커버하면 <strong>딱 한 번</strong> 관리자에게 알림 메일이 자동 발송됩니다.</p>
    <h3>발송 조건</h3>
    <ul>
      <li>등록된 참가자 중 최소 한 명씩이 관광지 1·2·3·4번을 각각 <strong>제출(잠금)</strong> 완료.</li>
      <li>이 조건이 충족되는 <strong>첫 시점</strong> 에 자동 발송. 이후 조건이 유지돼도 재발송하지 않음 (중복 방지).</li>
    </ul>
    <h3>강제 재발송</h3>
    <p>대시보드 하단의 <strong>“완료 알림 강제 발송”</strong> 버튼으로 언제든 수동 발송 가능. 메일이 안 왔거나 재확인이 필요할 때 사용하세요.</p>
  </section>

  <!-- 8. 비밀번호 변경 -->
  <section class="help-section" id="help-8" aria-labelledby="help-8-t">
    <h2 id="help-8-t" class="help-section-title">8. 관리자 비밀번호 변경</h2>
    <p>좌측 메뉴 <strong>“🔑 비밀번호 변경”</strong>.</p>
    <ol>
      <li><strong>현재 비밀번호</strong> 입력.</li>
      <li><strong>새 비밀번호</strong> 입력 (최소 8자, 12자 이상 권장).</li>
      <li><strong>새 비밀번호 확인</strong> 에 동일하게 한 번 더 입력.</li>
      <li><strong>“변경”</strong> 버튼 클릭.</li>
      <li>변경 후에는 로그아웃 → 새 비밀번호로 재로그인 → 정상 접속 확인.</li>
    </ol>
    <p class="hint">💡 새 비밀번호는 대소문자·숫자·특수문자를 섞은 12자 이상을 권장합니다. 사전 단어·이름·생일 등은 피하세요.</p>
  </section>

  <!-- 9. 참가자 상세·CSV 내보내기 -->
  <section class="help-section" id="help-9" aria-labelledby="help-9-t">
    <h2 id="help-9-t" class="help-section-title">9. 참가자별 상세 보기·CSV 내보내기</h2>
    <h3>참가자별 상세 보기</h3>
    <p>대시보드에서 참가자 이름 클릭 → 그 참가자의 <strong>모든 응답·업로드 파일</strong>을 볼 수 있습니다. 응답 진행 상황을 문항 단위로 확인 가능.</p>
    <h3>전체 응답 CSV 내보내기</h3>
    <ol>
      <li>대시보드 상단의 <strong>“CSV 다운로드”</strong> 버튼 클릭.</li>
      <li>모든 참가자의 조·이름·휴대폰·제출 상태·응답률 등이 담긴 CSV 파일이 다운로드됨.</li>
      <li>엑셀에서 열 때 한글이 깨지면, 파일을 메모장으로 먼저 열어 <strong>“다른 이름으로 저장 → 인코딩: UTF-8”</strong> 로 저장한 뒤 엑셀에서 여세요.</li>
    </ol>
  </section>

  <!-- 10. FAQ -->
  <section class="help-section" id="help-10" aria-labelledby="help-10-t">
    <h2 id="help-10-t" class="help-section-title">10. 자주 묻는 질문 (FAQ)</h2>

    <h3>Q. 참가자가 로그인이 안 된다고 합니다.</h3>
    <p>휴대폰번호가 <strong>정확히 등록된 번호와 일치</strong>해야 합니다. 참가자 관리에서 등록된 번호를 확인하고, 하이픈 없이 숫자만 입력하도록 안내하세요.</p>

    <h3>Q. 참가자가 답변 도중 실수로 제출했어요. 되돌릴 수 있나요?</h3>
    <p>대시보드 → 해당 참가자 이름 클릭 → 상세 화면에서 <strong>“제출 취소”</strong> 버튼으로 잠금 해제 가능. 그러면 참가자가 다시 답변·수정할 수 있습니다.</p>

    <h3>Q. 알림 메일이 도착하지 않아요.</h3>
    <ol>
      <li><strong>발신 설정</strong> → <strong>테스트 발송</strong> 시도. 오류 메시지 확인.</li>
      <li>Gmail 사용 중이면 앱 비밀번호가 맞는지 재확인 (일반 비밀번호는 안 됨).</li>
      <li>수신자 메일함의 <strong>스팸함</strong> 도 확인.</li>
      <li>여전히 안 되면 관리자에게 문의 (사이트 하단 푸터 참조).</li>
    </ol>

    <h3>Q. 사진·영상 업로드가 안 됩니다.</h3>
    <p>인피니티프리 무료 호스팅은 <strong>파일당 10MB 제한</strong>이 있습니다. 영상을 30초 이내로 짧게 촬영하거나, 720p 이하 해상도로 설정하도록 안내하세요.</p>

    <h3>Q. 참가자 페이지에서 특정 탭의 “미완료” 표시가 사라지지 않아요.</h3>
    <p>그 탭에 <strong>필수(required)</strong> 로 지정된 문항 중 답변 안 된 것이 남아있습니다. 참가자 상세 화면에서 어떤 문항이 비어있는지 확인할 수 있습니다.</p>
  </section>

  <p class="help-footer-note hint">
    도움말에 없는 문의는 사이트 하단 푸터의 연락처로 부탁드립니다. 이 도움말은 관리자만 볼 수 있습니다.
  </p>
</section>
