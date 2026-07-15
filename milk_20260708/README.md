# 홈페이지 키트 — v1.0 🎉

> 개인 홈페이지 설치 키트. 동인계 / 개인 사이트 / 작은 작업실용.
> PHP 7.4+ / MySQL 5.7+ / Apache (mod_rewrite 권장)

---

## 🎯 v1.0 = 모든 기본 기능 완성

| 영역 | 상태 |
|---|---|
| 설치 마법사 (3단계) | ✅ |
| Prefix 자동 치환 시스템 | ✅ |
| 4종 테마 (Paper / Linen / Ink / Midnight) | ✅ |
| 디폴트 메인 스킨 + 6종 블록 | ✅ |
| 디폴트 게시판 스킨 (List) | ✅ |
| PC 사이드바 + 사이트 라우터 | ✅ |
| 게시판 페이지 + 댓글 | ✅ |
| 관리자 페이지 (6개 섹션) | ✅ |
| 드래그 정렬 + AJAX 토글 | ✅ |
| 이미지 업로드 (배너) | ✅ |
| **모바일 상단바 + 하단 탭바 + 풀스크린 드로어** | ✅ |

→ **이제 바로 호스팅에 올려서 운영 가능한 완성 키트.**

---

## 🚀 설치 방법

1. 키트 폴더 전체를 호스팅에 업로드
2. `data/` 폴더에 쓰기 권한 부여 (`chmod 755 data/`)
3. 브라우저에서 `https://your-domain.com/install.php` 접속
4. 3단계 마법사:
   - **Step 1**: DB 정보 + 테이블 prefix (예: `milk_`)
   - **Step 2**: 관리자 ID/비밀번호 + 사이트 이름 + 기본 테마
   - **Step 3**: 완료
5. 보안을 위해 `install.php` + `install/` 폴더 삭제
6. 사이드바 하단 자물쇠 → 관리자 로그인 → `/admin/` 에서 사이트 꾸미기

---

## 📱 PC vs 모바일 자동 전환

**모바일 (≤ 768px):**
- 좌측 사이드바 자동 숨김
- 상단에 작은 사이트명 바
- 하단에 5칸 탭바: 핀 게시판 4개 + 더보기
- 더보기 → 풀스크린 드로어 (전체 메뉴 + 로그인)

**PC (≥ 769px):**
- 좌측 사이드바 (브랜드 + 메뉴 그룹 + 게시판 + 로그인)
- 본문은 우측 그리드

같은 메뉴 데이터 한 벌로 양쪽이 동작 — 관리자가 게시판마다 "모바일 핀(📌)" 체크박스만 켜면 그 게시판이 자동으로 모바일 하단 탭에 노출됨. 핀 4개를 넘으면 첫 4개만, 부족하면 첫 자리에 "홈"이 자동 채워짐.

---

## 🎛 관리자 페이지 (6개 탭)

| 탭 | 할 수 있는 것 |
|---|---|
| 사이트 정보 | 사이트명, 한 줄 설명, 인사말, 자기소개, 아바타 URL, 파비콘 |
| 디자인 | 4종 테마 라디오, 폰트 @import URL, 커스텀 CSS |
| 메뉴/게시판 | 그룹 CRUD, 게시판 CRUD, 모바일 핀 토글, 스킨 선택 |
| 메인 페이지 | 블록 추가/삭제, **드래그 정렬** (자동 저장), 노출 토글, JSON 옵션 편집 |
| 배너 | 본인 배너 (200×40), 친구 배너 추가/수정/삭제 — 이미지 업로드 |
| 프로필 & 링크 | 소셜 링크 (트위터, 인스타, 메일 등) |

---

## 📁 폴더 구조 (47개 파일)

```
homepage-kit/
├── install.php              ① 설치 마법사
├── index.php                ② 메인 라우터
├── config.php               ③ 부트스트랩
│
├── install/                 schema.sql + seed.php
├── lib/                     db / auth / csrf / menu / theme / block
├── inc/                     sidebar / footer (PC)
├── pages/                   home / board / view / write / edit
├── css/core.css             테마 무관 리셋 + 레이아웃
├── data/                    .db_secret / banners / uploads
│
├── admin/                   ★ 관리자
│   ├── index.php
│   ├── style.css
│   ├── script.js
│   └── sections/
│       ├── site.php
│       ├── design.php
│       ├── menu.php
│       ├── mainpage.php     ← 드래그 정렬
│       ├── banner.php       ← 이미지 업로드
│       └── profile.php
│
├── mobile/                  ★ 모바일 partial
│   ├── inc/
│   │   ├── topbar.php       상단 사이트명
│   │   ├── tabbar.php       하단 5칸 탭바
│   │   └── drawer.php       풀스크린 드로어
│   ├── css/mobile.css       모바일 전용 오버라이드
│   └── js/mobile.js         드로어 토글 + ESC + 자동 닫기
│
└── skins/
    ├── main/default/
    │   ├── index.php
    │   ├── style.css
    │   ├── info.php
    │   └── blocks/          (스킨 안에 같이 위치)
    │       ├── profile.php
    │       ├── recent.php
    │       ├── guestbook.php
    │       ├── friends.php
    │       ├── tags.php
    │       └── custom-html.php
    │
    └── board/list/
        ├── index.php
        ├── view.php
        ├── style.css
        └── info.php
```

---

## ✨ 핵심 설계 — Prefix 자동 치환

```php
// 스킨/블록 작성자가 쓰는 코드
qAll("SELECT * FROM {post} WHERE po_bo_id = ?", [$bo_id]);
//                          ↑
//                  자동으로 {prefix}_post 로 변환
```

설치 시 정한 prefix가 모든 SQL에 자동으로 붙음. 다른 사람이 만든 스킨을 가져와도 자동으로 본인 prefix 따라감.

---

## 🎨 4단계 자유도

| 단계 | 영향 받는 곳 | 방법 |
|---|---|---|
| 1. 테마 | `theme_preset` config | admin → 디자인 |
| 2. 블록 on/off + 순서 | `hp_main_block` | admin → 메인 페이지 (드래그) |
| 3. 커스텀 CSS | `data/custom.css` | admin → 디자인 textarea |
| 4. 스킨/블록 직접 편집 | `skins/.../*.php` | FTP/SSH |

---

## 🧩 메인 페이지 블록 옵션 가이드

admin → 메인 페이지에서 각 블록의 **옵션(JSON)** 칸에 입력하는 값. 모든 키는 선택이며,
비워두면 아래 기본값이 사용됨. 형식은 표준 JSON (큰따옴표 사용).

예) `{"limit": 6, "title": "최근 일기"}`

### `recent` — 최근 글
| 키 | 타입 | 기본값 | 설명 |
|---|---|---|---|
| `title` | string | `"최근 글"` | 블록 제목 |
| `limit` | int (1–20) | `6` | 표시할 글 개수 |
| `boards` | int[] | (전체) | 특정 게시판 ID 배열만 노출. 예: `[2,5]` |

### `profile` — 프로필 카드
| 키 | 타입 | 기본값 | 설명 |
|---|---|---|---|
| `show_banner` | bool | `true` | 상단 배너 노출 |
| `show_social` | bool | `true` | 소셜 링크 아이콘 노출 |
| `show_note` | bool | `true` | 자기소개 메모 노출 |

### `guestbook` — 방명록 미리보기
| 키 | 타입 | 기본값 | 설명 |
|---|---|---|---|
| `title` | string | `"방명록"` | 블록 제목 |
| `limit` | int (1–20) | `4` | 미리보기 개수 |
| `show_more_link` | bool | `false` | "더 보기" 링크 노출 |

### `friends` — 링크/친구 목록
| 키 | 타입 | 기본값 | 설명 |
|---|---|---|---|
| `title` | string | `"Links"` | 블록 제목 |
| `limit` | int (1–100) | `50` | 표시 개수 |

### `tags` — 태그 모음
| 키 | 타입 | 기본값 | 설명 |
|---|---|---|---|
| `title` | string | `"태그"` | 블록 제목 |
| `tags` | string[] | (자동) | 직접 지정할 태그 배열. 예: `["일상","후기"]` |

### `custom-html` — 자유 HTML
| 키 | 타입 | 기본값 | 설명 |
|---|---|---|---|
| `title` | string | `""` | 블록 제목 (비우면 제목 없음) |
| `html` | string | `""` | 삽입할 HTML 문자열 |

> 옵션 키는 각 블록 파일(`skins/main/default/blocks/*.php`) 상단에서 직접 확인·추가할 수 있음.

---

## 🛠 향후 (선택)

- 추가 게시판 스킨 — gallery, blog, diary
- 추가 메인 스킨 — three-col, grid-wall
- PWA — manifest, service worker
- 댓글 비밀번호, 비공개 글
- 이미지 업로드 (아바타, 파비콘)
- 메뉴 그룹/게시판 드래그 정렬
