# 🥛 Milk Kit — 커스텀 스킨 만들기 가이드

> **이 파일을 AI(Claude, ChatGPT, Gemini 등)에 통째로 붙여넣고 원하는 디자인을 설명하면 스킨을 자동 생성할 수 있어요.**

## 📁 스킨 구조

스킨은 `skins/board/{스킨이름}/` 폴더에 넣으면 됩니다.

```
skins/board/my_skin/
├── info.php      (필수) 스킨 메타데이터
├── index.php     (필수) 게시판 목록 페이지
├── view.php      (필수) 글 상세 페이지
├── style.css     (필수) 스킨 전용 CSS
├── _helpers.php  (선택) 전용 헬퍼 함수
├── _form.php     (선택) 글 작성/수정 폼 커스텀
├── _card.php     (선택) 카드 컴포넌트
└── *.js          (선택) 전용 JavaScript
```

### 최소 구성 (4파일)
`info.php` + `index.php` + `view.php` + `style.css` 만 있으면 작동합니다.
`_template/` 폴더를 복사해서 이름만 바꾸면 바로 시작할 수 있어요.

---

## 🔧 각 파일 역할

### info.php (메타데이터)
```php
<?php
return [
    'name'        => 'My Skin',        // 영문 이름
    'kr_name'     => '내 스킨',         // 표시 이름
    'description' => '스킨 설명',
    'author'      => 'your_name',
    'version'     => '1.0.0',
];
```

### index.php (게시판 목록)
`pages/board.php`에서 다음 변수들이 전달됩니다:

| 변수 | 타입 | 설명 |
|------|------|------|
| `$board` | array | 게시판 행 (`bo_id`, `bo_slug`, `bo_name`, `bo_skin`, ...) |
| `$bo_id` | int | 게시판 ID |
| `$posts` | array | 글 배열 (아래 "글 데이터" 참고) |
| `$page_num` | int | 현재 페이지 번호 (1~) |
| `$total_pages` | int | 전체 페이지 수 |
| `$total` | int | 전체 글 수 |
| `$current_cat` | string | 선택된 카테고리 (없으면 빈 문자열) |

### view.php (글 상세)
`pages/view.php`에서 다음 변수들이 전달됩니다:

| 변수 | 타입 | 설명 |
|------|------|------|
| `$post` | array | 글 행 (전체 컬럼) |
| `$board` | array | 게시판 행 |
| `$comments` | array | 댓글 배열 |
| `$flash` | array\|null | 일회성 알림 `['type' => 'success'\|'error', 'msg' => '...']` |

---

## 📋 글 데이터 ($post / $posts 각 행)

| 컬럼 | 타입 | 설명 |
|------|------|------|
| `po_id` | int | 글 ID |
| `po_bo_id` | int | 게시판 ID |
| `po_title` | string | 제목 |
| `po_subtitle` | string\|null | 부제목 |
| `po_content` | string | 본문 (마크다운) |
| `po_extra` | string\|null | 확장 데이터 (JSON) |
| `po_thumbnail` | string\|null | 썸네일 (파일명 또는 URL) |
| `po_category` | string\|null | 카테고리 이름 |
| `po_views` | int | 조회수 |
| `po_is_private` | int | 비공개 여부 (0=공개, 1=비공개) |
| `po_created_at` | string | 작성일시 |
| `po_updated_at` | string\|null | 수정일시 |

---

## 🛠 사용 가능한 헬퍼 함수

### 출력 · 이스케이프
| 함수 | 설명 |
|------|------|
| `h($text)` | HTML 이스케이프 (`htmlspecialchars`) |
| `hp_render_markdown($text)` | 마크다운 → HTML 변환 |

### URL 생성
| 함수 | 설명 |
|------|------|
| `hp_url('board', ['slug' => $slug])` | 게시판 목록 URL |
| `hp_url('view', ['po_id' => $id])` | 글 상세 URL |
| `hp_url('write', ['slug' => $slug])` | 새 글 작성 URL |
| `hp_url('guestbook')` | 방명록 URL |
| `HP_BASE` | 사이트 루트 경로 (상수) |

### 이미지
| 함수 | 설명 |
|------|------|
| `hp_post_images($post)` | 글의 이미지 배열 (po_extra.images → po_thumbnail 폴백) |
| `hp_post_image_url($filename, $board)` | 이미지 파일명 → 표시 URL 변환 |
| `hp_save_uploaded_image($tmp, $name, $size, &$err, $slug)` | 이미지 저장 |

### 기타
| 함수 | 설명 |
|------|------|
| `hp_config($key, $default)` | 사이트 설정 조회 |
| `hp_icon($name)` | Material Icons / Font Awesome 아이콘 |
| `hp_board_categories($board)` | 게시판 카테고리 배열 |
| `is_admin()` | 관리자 로그인 여부 |
| `csrf_input()` | CSRF hidden input 출력 |
| `hp_redirect($url)` | 리다이렉트 |

---

## 🎨 CSS 변수

테마 컬러는 관리자가 설정하며, CSS 변수로 사용할 수 있습니다:

```css
/* 텍스트 */
var(--ink)          /* 기본 텍스트 */
var(--ink-soft)     /* 부제목/보조 텍스트 */
var(--ink-mute)     /* 메타 정보·날짜 */

/* 배경 */
var(--paper)        /* 카드·콘텐츠 배경 */
var(--paper-2)      /* 약간 어두운 배경 (input 등) */
var(--bg)           /* 페이지 전체 배경 */

/* 강조색 */
var(--accent)       /* 메인 강조색 */
var(--accent-soft)  /* 강조색 연한 배경 */
var(--accent-2)     /* 보조 강조색 */

/* 테두리 */
var(--hair)         /* 기본 테두리 */
var(--hair-soft)    /* 연한 테두리·구분선 */

/* 레이아웃 */
var(--content-max)  /* 콘텐츠 최대 폭 (기본 720px) */
```

---

## 📦 공통 컴포넌트 (include로 사용)

```php
<?php include HP_PATH . '/inc/board-actions.php'; ?>
<!-- 카테고리 네비 + "새 글" 버튼 -->

<?php include HP_PATH . '/inc/post-actions.php'; ?>
<!-- 목록 / 공유 / 수정 / 삭제 버튼 -->

<?php include HP_PATH . '/inc/post-comments.php'; ?>
<!-- 댓글 목록 + 작성 폼 -->
```

---

## 🤖 AI에게 줄 프롬프트 예시

### 예시 1: 블로그형 카드 스킨
```
Milk Kit 게시판 스킨을 만들어줘.
README.md 사양에 맞춰서 index.php / view.php / style.css / info.php 4개 파일을 생성해줘.

요건:
- 블로그 느낌의 카드형 목록 (썸네일 왼쪽 + 제목 오른쪽)
- 상세 페이지는 헤더 이미지 + 본문
- 배색은 CSS 변수(--accent, --ink, --paper 등)를 사용
- 카테고리 칩은 .post-cat-chip 클래스 사용
- 페이지네이션, 카테고리 네비, 댓글은 공통 컴포넌트 include
```

### 예시 2: 포트폴리오·갤러리
```
Milk Kit 게시판 스킨을 만들어줘.

요건:
- 3단 Masonry 그리드
- 각 카드는 썸네일 메인, 호버 시 제목 표시
- 상세 페이지는 큰 이미지 + 설명
- 반응형 (모바일 2단, 520px 이하 1단)
```

### 예시 3: 타임라인
```
Milk Kit 게시판 스킨을 만들어줘.

요건:
- 세로 타임라인 형식
- 왼쪽에 날짜, 오른쪽에 카드 (제목 + 부제목 + 본문 미리보기)
- 카테고리별로 카드 왼쪽 보더 색상이 달라짐
```

---

## ⚠ 주의사항

1. **최외곽은 `<div class="list-wrap">`으로 감싸기** — `var(--content-max)` 폭 제한이 자동 적용
2. **CSS 파일은 직접 로드** — `<link rel="stylesheet" href="...">` 를 index.php/view.php 상단에
3. **CSS 클래스명에 스킨 고유 접두사 붙이기** — 다른 스킨과 충돌 방지 (예: `mysk-card`, `mysk-grid`)
4. **스킨 폴더명은 영소문자 + 언더스코어** — `my_skin` ○ / `My-Skin` ×
5. **`_`로 시작하는 폴더명은 내부용** — 사이드바 메뉴에 표시 안 됨 (`_template`, `_charlog` 등)
6. **JS가 필요하면** PHP 안에 `<script>` 쓰거나 별도 파일로 `<script src="...">` 로드

---

## 📂 스킨 만드는 순서

1. `skins/board/_template/` 폴더를 복사해서 `skins/board/my_skin/` 으로 이름 변경
2. `info.php`의 메타데이터 수정
3. `style.css` 자유롭게 커스텀
4. `index.php` / `view.php` 마크업 변경
5. 관리자 → 메뉴 관리 → 게시판의 "스킨"에서 `my_skin` 선택
6. 완성!

---

*Milk Kit v1.0*
