-- 2026 청년포럼 가고파 — 데이터베이스 스키마 + 샘플 데이터
-- 실행:  mysql -u root -p < schema.sql   또는 phpMyAdmin에서 임포트
-- 체크리스트 항목(관광지=대분류 탭, items)은 seed_checklist.php 로 시딩하거나
-- 관리자 → "체크리스트 관리" 에서 등록합니다.

-- InfinityFree 에서는 DB 를 컨트롤 패널에서 미리 만들어야 하고
-- 임포트 시 이미 그 DB 를 선택한 상태이므로 CREATE DATABASE / USE 는 사용하지 않습니다.
-- CREATE DATABASE IF NOT EXISTS gagopa
--   CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE gagopa;

-- 기존 테이블 정리 (재설치 시)
DROP TABLE IF EXISTS uploads;
DROP TABLE IF EXISTS completions;
DROP TABLE IF EXISTS submissions;
DROP TABLE IF EXISTS responses;
DROP TABLE IF EXISTS checks;
DROP TABLE IF EXISTS items;
DROP TABLE IF EXISTS spots;
DROP TABLE IF EXISTS members;
DROP TABLE IF EXISTS teams;

-- 조
CREATE TABLE teams (
  id   INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(50) NOT NULL
) ENGINE=InnoDB;

-- 참가자 (휴대폰번호로 로그인)
CREATE TABLE members (
  id      INT AUTO_INCREMENT PRIMARY KEY,
  team_id INT NOT NULL,
  name    VARCHAR(50) NOT NULL,
  phone   VARCHAR(20) NOT NULL UNIQUE,   -- 숫자만 저장 (하이픈 제거)
  FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 대분류(탭): 설문조사 / 이동권 / 시설편의 / 정보접근권 / 문화향유권
CREATE TABLE spots (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(80) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0
) ENGINE=InnoDB;

-- 체크리스트 항목 (설문 문항 / 지표 항목)
CREATE TABLE items (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  spot_id    INT NOT NULL,                 -- 소속 대분류(탭)
  section    VARCHAR(150) NULL,            -- 탭 안 소제목(그룹 헤더)
  label      VARCHAR(500) NOT NULL,        -- 문항/확인 포인트
  hint       TEXT NULL,                    -- 근거·기준 등 도움말
  type       VARCHAR(12) NOT NULL DEFAULT 'check',  -- radio | check | text
  options    TEXT NULL,                    -- radio 선택지 ('|' 구분)
  has_note   TINYINT(1) NOT NULL DEFAULT 0,-- '개선사항' 서술란(선택) 표시
  required   TINYINT(1) NOT NULL DEFAULT 1,-- 제출 필수 여부
  sort_order INT NOT NULL DEFAULT 0,
  FOREIGN KEY (spot_id) REFERENCES spots(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 개인별 응답 (참가자마다 자신의 응답을 작성)
CREATE TABLE responses (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  member_id  INT NOT NULL,
  item_id    INT NOT NULL,
  value      TEXT NULL,                    -- 라디오 선택값 / 텍스트 내용 / 체크 '1'
  note       TEXT NULL,                    -- 개선사항(선택)
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uniq_member_item (member_id, item_id),
  FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
  FOREIGN KEY (item_id)   REFERENCES items(id)   ON DELETE CASCADE
) ENGINE=InnoDB;

-- 제출(잠금) 기록 — 참가자당 1행
CREATE TABLE submissions (
  member_id     INT PRIMARY KEY,
  surveyor_type VARCHAR(30) NULL,          -- 조사원 구분(장애유형)
  wheelchair    TINYINT(1) NULL,           -- 휠체어 사용 여부 (1=사용, 0=미사용, NULL=미입력)
  vision_detail VARCHAR(20) NULL,          -- 시각장애 세부 (전맹/저시력)
  site_name     VARCHAR(120) NULL,         -- 관광지명
  submitted_at  DATETIME NOT NULL,
  FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 완료 기록 — 조 단위(팀이 기준 인원 이상 제출 → 그 조 1행). 조별 메일 1회, 중복 방지.
CREATE TABLE completions (
  team_id      INT PRIMARY KEY,
  completed_at DATETIME NOT NULL,
  email_sent   TINYINT(1) NOT NULL DEFAULT 0,
  email_error  VARCHAR(255) NULL,
  FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 관리자 설정(발신 SMTP 계정 등). config.php 상수를 기본값으로 덮어씀.
CREATE TABLE settings (
  skey   VARCHAR(64) PRIMARY KEY,
  svalue TEXT NULL
) ENGINE=InnoDB;

-- 참가자가 각 탭(관광지) 아래에 올린 사진·영상 업로드
CREATE TABLE uploads (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  member_id     INT NOT NULL,
  spot_id       INT NOT NULL,
  kind          ENUM('image','video') NOT NULL,
  orig_name     VARCHAR(255) NOT NULL,
  stored_path   VARCHAR(255) NOT NULL,        -- uploads/ 기준 상대경로
  mime          VARCHAR(80) NOT NULL,
  size_bytes    INT NOT NULL,
  uploaded_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (member_id, spot_id),
  FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
  FOREIGN KEY (spot_id)   REFERENCES spots(id)   ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────────────────
-- 샘플 데이터 (실제 값으로 교체하세요)
-- ─────────────────────────────────────────────────────────

-- 조 3개 (1·2·3조 / 인원 7·7·6명은 관리자에서 참가자 등록)
INSERT INTO teams (id, name) VALUES
 (1,'1조'), (2,'2조'), (3,'3조');

-- 참가자 (테스트용 소수) — 실제 참가자는 관리자 → 참가자 관리에서 등록/CSV 업로드
INSERT INTO members (team_id, name, phone) VALUES
 (1,'홍길동','01011110001'),
 (1,'김철수','01011110002'),
 (2,'이영희','01022220001'),
 (3,'박민수','01033330001');

-- 대분류 탭 + 항목은 seed_checklist.php 로 시딩합니다:
--   C:\xampp\php\php.exe seed_checklist.php