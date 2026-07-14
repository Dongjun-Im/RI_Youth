<?php
require_once __DIR__ . '/config.php';

/**
 * PDO 연결 (싱글턴).
 * 테스트에서는 db($testPdo) 로 다른 연결(예: SQLite)을 주입할 수 있습니다.
 * 인자 없이 호출하면 config.php 설정으로 MySQL에 연결합니다.
 */
function db(?PDO $inject = null): PDO {
    static $pdo = null;
    if ($inject !== null) {
        $pdo = $inject;
        return $pdo;
    }
    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            DB_HOST, DB_PORT, DB_NAME);
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

/**
 * "중복이면 무시하고 INSERT" 구문을 현재 DB 드라이버에 맞게 반환.
 *   MySQL  → "INSERT IGNORE"
 *   SQLite → "INSERT OR IGNORE"
 */
function sql_insert_ignore(): string {
    return db()->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite'
        ? 'INSERT OR IGNORE'
        : 'INSERT IGNORE';
}
