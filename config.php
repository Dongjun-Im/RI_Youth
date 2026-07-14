<?php
/**
 * 2026 청년포럼 가고파 — 환경설정
 * 이 파일의 값만 바꾸면 됩니다. (실제 배포 전 반드시 수정)
 *
 * 참고: 아래 설정값은 define() + if(!defined()) 가드로 정의되어 있어,
 * 테스트 부트스트랩(tests/bootstrap.php) 등에서 이 파일을 불러오기 전에
 * 원하는 값을 미리 define() 해두면 그 값이 우선 적용됩니다.
 * (기본 동작/기본값은 기존과 완전히 동일합니다.)
 */

/** 이미 정의돼 있지 않은 설정만 기본값으로 채운다 */
function cfg_default(string $name, $value): void {
    if (!defined($name)) define($name, $value);
}

// ── 앱 제목 ──────────────────────────────────────────────
cfg_default('APP_TITLE', '2026 청년포럼 가고파주');

// ── 관광지 목록 (제출 시 콤보상자에서 선택) ──────────────
// 완료 메일은 아래 목록 중 앞에서부터 REQUIRED_SITE_COUNT 개(관광지 1~4번)가
// 각각 완료 제출되면 발송됩니다.
cfg_default('SITE_LIST', [
    '마장호수(킹카누)',
    '헤이리예술마을(도자기체험)',
    'DMZ 평화관광(도라전망대)',
    '임진각 평화누리공원(평화곤돌라)',
    '뮤지엄헤이(전시관)',
]);
cfg_default('REQUIRED_SITE_COUNT', 4);   // 완료 조건: 관광지 1~4번 커버

// ── 데이터베이스 (MySQL / MariaDB) ───────────────────────
cfg_default('DB_HOST', '127.0.0.1');
cfg_default('DB_PORT', 3306);
cfg_default('DB_NAME', 'gagopa');
cfg_default('DB_USER', 'root');
cfg_default('DB_PASS', '');            // XAMPP 기본값은 빈 문자열

// ── 관리자 ───────────────────────────────────────────────
cfg_default('ADMIN_PASSWORD', 'change-me-1234');   // 관리자 로그인 비밀번호 (배포 전 변경!)

// ── 완료 알림 메일 ───────────────────────────────────────
// 조가 체크리스트를 100% 완료하면 아래 주소로 자동 알림이 발송됩니다.
cfg_default('NOTIFY_TO', 'anycall4518@gmail.com');   // 운영진(관리자) 수신 메일
cfg_default('NOTIFY_TO_NAME', '가고파 운영진');

// ── SMTP (보내는 메일 서버) ──────────────────────────────
// Gmail 사용 시: 2단계 인증 후 "앱 비밀번호"를 발급받아 SMTP_PASS 에 넣으세요.
//   https://myaccount.google.com/apppasswords
cfg_default('SMTP_HOST', 'smtp.gmail.com');
cfg_default('SMTP_PORT', 465);                 // 465(SSL) 권장, 587(STARTTLS)도 지원
cfg_default('SMTP_USER', 'your-account@gmail.com');
cfg_default('SMTP_PASS', 'xxxx xxxx xxxx xxxx');   // Gmail 앱 비밀번호 (공백 포함 그대로)
cfg_default('SMTP_FROM', 'your-account@gmail.com');
cfg_default('SMTP_FROM_NAME', '2026 청년포럼 가고파');

// 메일을 실제로 보내지 않고 로그만 남기려면 true (테스트용)
cfg_default('MAIL_DRY_RUN', false);

// ── 기타 ────────────────────────────────────────────────
date_default_timezone_set('Asia/Seoul');
