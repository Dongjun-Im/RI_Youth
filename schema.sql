-- 2026 청년포럼 가고파 — 데이터베이스 스키마 + 샘플 데이터
-- 실행:  mysql -u root -p < schema.sql   또는 phpMyAdmin에서 임포트
-- 체크리스트 항목(관광지=대분류 탭, items)은 seed_checklist.php 로 시딩하거나
-- 관리자 → "체크리스트 관리" 에서 등록합니다.

CREATE DATABASE IF NOT EXISTS gagopa
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE gagopa;

-- 기존 테이블 정리 (재설치 시)
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
  site_name     VARCHAR(120) NULL,         -- 관광지명
  submitted_at  DATETIME NOT NULL,
  FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 전체 완료 기록 (완료 조건 충족 → 메일 1회, 중복 방지). 단일 행(id=1)만 사용.
CREATE TABLE completions (
  id           INT PRIMARY KEY,            -- 항상 1
  completed_at DATETIME NOT NULL,
  email_sent   TINYINT(1) NOT NULL DEFAULT 0,
  email_error  VARCHAR(255) NULL
) ENGINE=InnoDB;

-- 관리자 설정(발신 SMTP 계정 등). config.php 상수를 기본값으로 덮어씀.
CREATE TABLE settings (
  skey   VARCHAR(64) PRIMARY KEY,
  svalue TEXT NULL
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────────────────
-- 샘플 데이터 (실제 값으로 교체하세요)
-- ─────────────────────────────────────────────────────────

-- 조 5개
INSERT INTO teams (id, name) VALUES
 (1,'1조'), (2,'2조'), (3,'3조'), (4,'4조'), (5,'5조');

-- 참가자 (휴대폰번호는 숫자만, 하이픈 없이 저장) — 테스트용, 실제 값으로 교체
INSERT INTO members (team_id, name, phone) VALUES
 (1,'홍길동','01011110001'),
 (1,'김철수','01011110002'),
 (2,'이영희','01022220001'),
 (3,'박민수','01033330001'),
 (4,'최지우','01044440001'),
 (5,'정하나','01055550001');

-- 대분류 탭 + 항목은 seed_checklist.php 로 시딩합니다:
--   C:\xampp\php\php.exe seed_checklist.php