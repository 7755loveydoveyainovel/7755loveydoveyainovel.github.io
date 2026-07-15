-- ═══════════════════════════════════════════════════════════════
--  홈페이지 키트 — 초기 스키마
-- ═══════════════════════════════════════════════════════════════
--
--  {tablename} 패턴은 install.php 가 사용자 선택 prefix 로 자동 치환.
--  예: prefix = 'milk_' → {config} → milk_config
--
--  실행 순서: install.php 가 세미콜론으로 분할 후 한 문장씩 PDO::exec()
--  모든 테이블은 InnoDB + utf8mb4 + utf8mb4_unicode_ci

SET FOREIGN_KEY_CHECKS = 0;

-- ─── 1. 사이트 설정 (key/value) ───
CREATE TABLE IF NOT EXISTS {config} (
  cfg_key   VARCHAR(64) PRIMARY KEY,
  cfg_value TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 2. 관리자 ───
CREATE TABLE IF NOT EXISTS {admin} (
  ad_id          INT AUTO_INCREMENT PRIMARY KEY,
  ad_login       VARCHAR(40) NOT NULL UNIQUE,
  ad_pw_hash     VARCHAR(255) NOT NULL,
  ad_last_login  DATETIME NULL,
  ad_created_at  DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 3. 세션 ───
CREATE TABLE IF NOT EXISTS {session} (
  ss_id          VARCHAR(64) PRIMARY KEY,
  ss_ad_id       INT NOT NULL,
  ss_ip          VARCHAR(45),
  ss_user_agent  VARCHAR(255),
  ss_expires_at  DATETIME NOT NULL,
  INDEX (ss_expires_at),
  FOREIGN KEY (ss_ad_id) REFERENCES {admin}(ad_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 4. 메뉴 그룹 ───
CREATE TABLE IF NOT EXISTS {menu_group} (
  mg_id            INT AUTO_INCREMENT PRIMARY KEY,
  mg_name          VARCHAR(60) NOT NULL,
  mg_icon          VARCHAR(40),
  mg_order         INT NOT NULL DEFAULT 0,
  mg_is_divider    TINYINT NOT NULL DEFAULT 0,
  mg_special_link  VARCHAR(40) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 5. 게시판 ───
CREATE TABLE IF NOT EXISTS {board} (
  bo_id              INT AUTO_INCREMENT PRIMARY KEY,
  bo_slug            VARCHAR(60) NOT NULL UNIQUE,
  bo_name            VARCHAR(60) NOT NULL,
  bo_icon            VARCHAR(40),
  bo_skin            VARCHAR(40) NOT NULL DEFAULT 'list',
  bo_mg_id           INT NULL,
  bo_order           INT NOT NULL DEFAULT 0,
  bo_mobile_pinned   TINYINT NOT NULL DEFAULT 0,
  bo_mobile_order    INT NOT NULL DEFAULT 0,
  bo_admin_only      TINYINT NOT NULL DEFAULT 0,
  bo_created_at      DATETIME NOT NULL,
  INDEX (bo_mg_id),
  INDEX (bo_mobile_pinned, bo_mobile_order),
  FOREIGN KEY (bo_mg_id) REFERENCES {menu_group}(mg_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 6. 게시글 ───
CREATE TABLE IF NOT EXISTS {post} (
  po_id          INT AUTO_INCREMENT PRIMARY KEY,
  po_bo_id       INT NOT NULL,
  po_title       VARCHAR(200) NOT NULL,
  po_subtitle    VARCHAR(200) NULL,
  po_content     LONGTEXT NOT NULL,
  po_extra       LONGTEXT NULL,
  po_thumbnail   VARCHAR(255) NULL,
  po_views       INT NOT NULL DEFAULT 0,
  po_created_at  DATETIME NOT NULL,
  po_updated_at  DATETIME NULL,
  INDEX (po_bo_id, po_created_at),
  FOREIGN KEY (po_bo_id) REFERENCES {board}(bo_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 7. 댓글 ───
CREATE TABLE IF NOT EXISTS {comment} (
  cm_id          INT AUTO_INCREMENT PRIMARY KEY,
  cm_po_id       INT NOT NULL,
  cm_parent_id   INT NULL,
  cm_name        VARCHAR(40) NOT NULL,
  cm_pw_hash     VARCHAR(255) NULL,
  cm_content     TEXT NOT NULL,
  cm_created_at  DATETIME NOT NULL,
  INDEX (cm_po_id),
  FOREIGN KEY (cm_po_id) REFERENCES {post}(po_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 8. 방명록 ───
CREATE TABLE IF NOT EXISTS {guestbook} (
  gb_id          INT AUTO_INCREMENT PRIMARY KEY,
  gb_name        VARCHAR(40) NOT NULL,
  gb_pw_hash     VARCHAR(255) NULL,
  gb_content     TEXT NOT NULL,
  gb_is_private  TINYINT NOT NULL DEFAULT 0,
  gb_is_pinned   TINYINT NOT NULL DEFAULT 0,
  gb_parent_id   INT NULL,
  gb_created_at  DATETIME NOT NULL,
  INDEX (gb_created_at),
  INDEX (gb_parent_id),
  INDEX (gb_is_pinned)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 9. 메인 페이지 블록 ★ ───
CREATE TABLE IF NOT EXISTS {main_block} (
  mb_id         INT AUTO_INCREMENT PRIMARY KEY,
  mb_type       VARCHAR(40) NOT NULL,
  mb_order      INT NOT NULL DEFAULT 0,
  mb_is_active  TINYINT NOT NULL DEFAULT 1,
  mb_options    LONGTEXT NULL,
  INDEX (mb_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 10. 배너 (본인 + 친구) ───
CREATE TABLE IF NOT EXISTS {banner} (
  bn_id          INT AUTO_INCREMENT PRIMARY KEY,
  bn_type        ENUM('self','friend') NOT NULL,
  bn_image       VARCHAR(255) NOT NULL,
  bn_url         VARCHAR(500) NULL,
  bn_title       VARCHAR(100) NULL,
  bn_order       INT NOT NULL DEFAULT 0,
  bn_created_at  DATETIME NOT NULL,
  INDEX (bn_type, bn_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 11. 프로필 소셜 링크 ───
CREATE TABLE IF NOT EXISTS {link} (
  lk_id     INT AUTO_INCREMENT PRIMARY KEY,
  lk_icon   VARCHAR(40) NOT NULL,
  lk_label  VARCHAR(40) NOT NULL,
  lk_url    VARCHAR(500) NOT NULL,
  lk_order  INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 12. 방문 카운터 ───
CREATE TABLE IF NOT EXISTS {visit} (
  vs_date   DATE PRIMARY KEY,
  vs_count  INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
