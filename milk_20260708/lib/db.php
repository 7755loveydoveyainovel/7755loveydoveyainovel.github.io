<?php
/**
 * lib/db.php — PDO 래퍼 + 테이블 prefix 자동 치환
 *
 * ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
 *  핵심 기능: SQL 안의 {tablename} 을 자동으로 HP_PREFIX 로 치환
 * ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
 *
 *  사용 예 (스킨/블록 작성자):
 *      qAll("SELECT * FROM {post} WHERE po_bo_id = ?", [$id]);
 *      qOne("SELECT * FROM {board} WHERE bo_slug = ?", [$slug]);
 *      qInsert("INSERT INTO {comment} (cm_po_id, cm_name) VALUES (?, ?)", [$pid, $name]);
 *
 *  내부 변환:
 *      {post}    → milk_post   (사용자가 install에서 'milk_'로 정한 경우)
 *      {board}   → milk_board
 *      {comment} → milk_comment
 *
 *  → 스킨 코드에 prefix 가 절대 하드코딩되지 않음.
 *    install 단계에서 prefix 만 바꾸면 모든 스킨이 그대로 작동.
 */

$GLOBALS['_db'] = null;

function db_connect($host, $name, $user, $pass) {
    try {
        $dsn = "mysql:host={$host};dbname={$name};charset=utf8mb4";
        $GLOBALS['_db'] = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e) {
        // 진짜 에러는 server log 에만 — 사용자 화면에는 일반 메시지
        // (DB 호스트/유저/경로가 노출되는 걸 방지)
        error_log('DB connect failed: ' . $e->getMessage());
        die('데이터베이스 연결에 문제가 있어요. 잠시 후 다시 시도해주세요.');
    }
}

function db() {
    return $GLOBALS['_db'];
}

/**
 * {tablename} → HP_PREFIX . tablename
 *
 * 정규식은 영문자/숫자/언더스코어만 허용해서 SQL 인젝션을 막음.
 * 백틱이 없는 평문 식별자만 매칭.
 */
function db_prefix_sql($sql) {
    return preg_replace_callback('/\{([a-z_][a-z0-9_]*)\}/i', function ($m) {
        return HP_PREFIX . $m[1];
    }, $sql);
}

/**
 * 쿼리 실행 → PDOStatement
 */
function q($sql, $params = []) {
    $sql  = db_prefix_sql($sql);
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

/** 첫 행 또는 null */
function qOne($sql, $params = []) {
    $row = q($sql, $params)->fetch();
    return $row ?: null;
}

/** 전체 행 (배열) */
function qAll($sql, $params = []) {
    return q($sql, $params)->fetchAll();
}

/** 단일 값 (첫 행, 첫 컬럼) */
function qVal($sql, $params = []) {
    $row = q($sql, $params)->fetch(PDO::FETCH_NUM);
    return $row ? $row[0] : null;
}

/** INSERT 후 lastInsertId */
function qInsert($sql, $params = []) {
    q($sql, $params);
    return db()->lastInsertId();
}

/** UPDATE/DELETE 후 영향받은 행 수 */
function qExec($sql, $params = []) {
    return q($sql, $params)->rowCount();
}

/** 트랜잭션 헬퍼 */
function qTransaction(callable $fn) {
    db()->beginTransaction();
    try {
        $result = $fn();
        db()->commit();
        return $result;
    } catch (Throwable $e) {
        db()->rollBack();
        throw $e;
    }
}

/**
 * 스키마 자동 업그레이드
 *
 * 새 버전에서 추가된 컬럼들을 ALTER TABLE 로 살짝 추가해줘서
 * 기존 설치도 새 기능을 쓸 수 있게 함. 컬럼이 이미 있으면 아무것도 안 함.
 *
 * 이 함수는 어드민 진입 시 1회 호출되며, 가벼운 information_schema 쿼리만 함.
 */
function hp_schema_upgrade() {
    // 스키마 버전 — 새 컬럼 추가 시 올리면 기존 세션도 재검사
    $schema_ver = 3;
    if (($_SESSION['_schema_checked'] ?? 0) >= $schema_ver) return;

    $checks = [
        // [테이블, 컬럼명, 추가할 SQL 조각]
        ['menu_group', 'mg_is_divider',   'ADD COLUMN mg_is_divider TINYINT NOT NULL DEFAULT 0'],
        ['menu_group', 'mg_special_link', 'ADD COLUMN mg_special_link VARCHAR(40) NULL'],
        ['menu_group', 'mg_mobile_pinned','ADD COLUMN mg_mobile_pinned TINYINT NOT NULL DEFAULT 0'],
        ['guestbook',  'gb_parent_id',    'ADD COLUMN gb_parent_id INT NULL'],
        ['guestbook',  'gb_is_pinned',    'ADD COLUMN gb_is_pinned TINYINT NOT NULL DEFAULT 0'],
        ['post',       'po_subtitle',     'ADD COLUMN po_subtitle VARCHAR(200) NULL'],
        ['comment',    'cm_pw_hash',      'ADD COLUMN cm_pw_hash VARCHAR(255) NULL'],
        ['comment',    'cm_is_private',   'ADD COLUMN cm_is_private TINYINT NOT NULL DEFAULT 0'],
        ['comment',    'cm_parent_id',    'ADD COLUMN cm_parent_id INT NULL'],
        ['board',      'bo_categories',   'ADD COLUMN bo_categories TEXT NULL'],
        ['post',       'po_category',     'ADD COLUMN po_category VARCHAR(40) NULL'],
        ['post',       'po_is_private',   'ADD COLUMN po_is_private TINYINT NOT NULL DEFAULT 0'],
        ['post',       'po_pw_hash',      'ADD COLUMN po_pw_hash VARCHAR(255) NULL'],
        ['board',      'bo_password',     'ADD COLUMN bo_password VARCHAR(255) NULL'],
        ['menu_group', 'mg_section',      'ADD COLUMN mg_section VARCHAR(40) NULL'],
        ['menu_group', 'mg_sec_id',       'ADD COLUMN mg_sec_id INT NULL'],
    ];

    foreach ($checks as [$table, $col, $sql]) {
        $exists = qVal(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME   = ?
               AND COLUMN_NAME  = ?",
            [HP_PREFIX . $table, $col]
        );
        if (!$exists) {
            try {
                qExec("ALTER TABLE {" . $table . "} $sql");
            } catch (Throwable $e) {
                // 권한 부족 등 → 무시
            }
        }
    }

    // 새 테이블 — 컬럼 추가가 아니라 테이블 자체 생성
    $new_tables = [
        'section' => "CREATE TABLE IF NOT EXISTS {section} (
            sec_id INT AUTO_INCREMENT PRIMARY KEY,
            sec_name VARCHAR(40) NOT NULL,
            sec_icon VARCHAR(40) NULL,
            sec_order INT NOT NULL DEFAULT 0,
            sec_created_at DATETIME NOT NULL,
            INDEX (sec_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ];
    foreach ($new_tables as $table => $sql) {
        $exists = qVal(
            "SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?",
            [HP_PREFIX . $table]
        );
        if (!$exists) {
            try {
                qExec($sql);
            } catch (Throwable $e) {
                // 권한 부족 → 사용자가 직접 phpMyAdmin 에서 생성
            }
        }
    }

    $_SESSION['_schema_checked'] = $schema_ver;
}
