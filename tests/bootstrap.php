<?php
/**
 * 테스트 부트스트랩
 * ------------------------------------------------------------------
 * 실제 MySQL 서버 없이도 앱 로직을 검증할 수 있도록,
 * 인메모리 SQLite 데이터베이스를 만들어 db() 에 주입합니다.
 *
 *  - 메일은 실제로 보내지 않도록 MAIL_DRY_RUN=true 로 강제 (로그만 기록)
 *  - MySQL 전용 구문(NOW(), INSERT IGNORE)은 SQLite에 맞게 자동 처리
 *      · NOW()        → SQLite 사용자 함수로 등록
 *      · INSERT IGNORE → sql_insert_ignore() 가 드라이버별로 반환
 *
 * 앱 코드(config/db/lib)는 그대로 사용하며, 여기서 값만 미리 정의/주입합니다.
 */

error_reporting(E_ALL);
// CLI에서 세션 함수(session_regenerate_id 등)가 헤더 경고를 내지 않도록 버퍼링
if (PHP_SAPI === 'cli' && ob_get_level() === 0) {
    ob_start();
}
ini_set('session.save_path', sys_get_temp_dir());

// config.php 가 로드되기 전에 테스트용 값을 미리 정의 (가드 define 로 우선 적용됨)
define('MAIL_DRY_RUN', true);                 // 실제 발송 금지, mail_dryrun.log 에만 기록
define('NOTIFY_TO', 'test-operator@example.com');
define('NOTIFY_TO_NAME', '테스트 운영진');
define('ADMIN_PASSWORD', 'test-admin-pw');
// 접속 허용 기간을 '지금'을 포함하도록 정의 (participant_access_open 테스트용)
define('ACCESS_START', date('Y-m-d H:i:s', time() - 86400));
define('ACCESS_END',   date('Y-m-d H:i:s', time() + 86400));

$ROOT = dirname(__DIR__);

// dry-run 로그 초기화 (완료→메일 경로 검증에 사용)
define('DRYRUN_LOG', $ROOT . '/mail_dryrun.log');
@unlink(DRYRUN_LOG);

require_once $ROOT . '/lib.php';   // config.php, db.php 포함

/**
 * 인메모리 SQLite 연결을 만들고 스키마를 생성하여 db() 에 주입한다.
 * 매 호출마다 완전히 새 DB(빈 상태 + 샘플 시드) 로 초기화된다.
 */
function reset_test_db(): PDO {
    $pdo = new PDO('sqlite::memory:', null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    // MySQL 의 NOW() 를 SQLite 에서도 쓸 수 있게 등록
    $pdo->sqliteCreateFunction('NOW', fn() => date('Y-m-d H:i:s'), 0);
    // FK CASCADE(조/관광지 삭제 시 하위 정리)를 MySQL 과 동일하게 검증하기 위해 활성화
    $pdo->exec('PRAGMA foreign_keys = ON');

    $pdo->exec('
        CREATE TABLE teams (
            id   INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL
        );
        CREATE TABLE members (
            id      INTEGER PRIMARY KEY AUTOINCREMENT,
            team_id INTEGER NOT NULL,
            name    TEXT NOT NULL,
            phone   TEXT NOT NULL UNIQUE,
            FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE
        );
        CREATE TABLE spots (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            name       TEXT NOT NULL,
            sort_order INTEGER NOT NULL DEFAULT 0
        );
        CREATE TABLE items (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            spot_id    INTEGER NOT NULL,
            section    TEXT NULL,
            label      TEXT NOT NULL,
            hint       TEXT NULL,
            type       TEXT NOT NULL DEFAULT "check",
            options    TEXT NULL,
            has_note   INTEGER NOT NULL DEFAULT 0,
            required   INTEGER NOT NULL DEFAULT 1,
            sort_order INTEGER NOT NULL DEFAULT 0,
            FOREIGN KEY (spot_id) REFERENCES spots(id) ON DELETE CASCADE
        );
        CREATE TABLE responses (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            member_id  INTEGER NOT NULL,
            item_id    INTEGER NOT NULL,
            value      TEXT NULL,
            note       TEXT NULL,
            updated_at TEXT NOT NULL,
            UNIQUE (member_id, item_id),
            FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
            FOREIGN KEY (item_id)   REFERENCES items(id)   ON DELETE CASCADE
        );
        CREATE TABLE submissions (
            member_id     INTEGER PRIMARY KEY,
            surveyor_type TEXT NULL,
            wheelchair    INTEGER NULL,
            vision_detail TEXT NULL,
            site_name     TEXT NULL,
            submitted_at  TEXT NOT NULL,
            FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE
        );
        CREATE TABLE completions (
            team_id      INTEGER PRIMARY KEY,
            completed_at TEXT NOT NULL,
            email_sent   INTEGER NOT NULL DEFAULT 0,
            email_error  TEXT NULL,
            FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE
        );
        CREATE TABLE settings (
            skey   TEXT PRIMARY KEY,
            svalue TEXT NULL
        );
    ');

    // ── 조 / 참가자 시드 (팀 완료 임계값 테스트 위해 1조에 6명) ──
    $pdo->exec("INSERT INTO teams (id,name) VALUES (1,'1조'),(2,'2조'),(3,'3조')");
    $pdo->exec("INSERT INTO members (id,team_id,name,phone) VALUES
        (1,1,'홍길동','01011110001'),
        (2,1,'김철수','01011110002'),
        (3,1,'박영수','01011110003'),
        (4,1,'최민호','01011110004'),
        (5,1,'정다은','01011110005'),
        (6,1,'강하늘','01011110006'),
        (7,2,'이영희','01022220001'),
        (8,2,'오세훈','01022220002'),
        (9,3,'박민수','01033330001')");

    // ── 대분류 탭 시드 ──
    $pdo->exec("INSERT INTO spots (id,name,sort_order) VALUES
        (1,'설문조사',1),(2,'이동권',2),(3,'시설편의',3),(4,'정보접근권',4),(5,'문화향유권',5)");

    // ── 항목 시드: 라디오(필수) 5 + 텍스트(선택) 1 = 총 6, 필수 5 ──
    // 설문조사(5점 척도, 필수) 3개
    $likert = '매우 그렇지 않다|그렇지 않다|보통|그렇다|매우 그렇다';
    $pdo->exec("INSERT INTO items (spot_id,section,label,type,options,has_note,required,sort_order) VALUES
        (1,'Ⅰ 전반 점검','문항 1','radio','$likert',0,1,1),
        (1,'Ⅰ 전반 점검','문항 2','radio','$likert',0,1,2),
        (1,'Ⅰ 전반 점검','문항 3','radio','$likert',0,1,3)");
    // 이동권(적합/애매/미흡, 필수 + 개선사항) 2개
    $pdo->exec("INSERT INTO items (spot_id,section,label,type,options,has_note,required,sort_order) VALUES
        (2,'Ⅰ 주차장','전용 주차구역 크기','radio','적합|애매|미흡',1,1,1),
        (2,'Ⅰ 주차장','주차구역 위치','radio','적합|애매|미흡',1,1,2)");
    // 기타 의견(텍스트, 선택) 1개
    $pdo->exec("INSERT INTO items (spot_id,section,label,type,has_note,required,sort_order) VALUES
        (1,'Ⅱ 의견','기타 의견','text',0,0,4)");

    db($pdo);   // 앱 전역에서 이 연결을 쓰도록 주입
    return $pdo;
}
