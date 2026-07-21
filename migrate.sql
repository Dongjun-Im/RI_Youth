-- 업데이트 마이그레이션 (팀 완료 · 조사원 세부 · 조 3개)
-- phpMyAdmin > (내 DB 선택) > Import(가져오기) 탭에서 이 파일을 선택해 실행하세요.

ALTER TABLE submissions
  ADD COLUMN wheelchair TINYINT(1) NULL AFTER surveyor_type,
  ADD COLUMN vision_detail VARCHAR(20) NULL AFTER wheelchair;

DROP TABLE IF EXISTS completions;
CREATE TABLE completions (
  team_id INT PRIMARY KEY,
  completed_at DATETIME NOT NULL,
  email_sent TINYINT(1) NOT NULL DEFAULT 0,
  email_error VARCHAR(255) NULL,
  FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 4·5조 정리 (해당 조에 참가자가 없을 때만 실행됨: 있으면 이 줄은 지우고 실행)
DELETE FROM teams WHERE id IN (4,5);
