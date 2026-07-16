/**
 * 拉比的工具箱 - 卿卿我我對話美化轉換器
 * 核心應用程式邏輯
 */

// 預設對話主題與 CSS 定義
const THEMES = [
  {
    id: "basic",
    name: "基礎",
    css: `
      @import url('https://fonts.googleapis.com/css2?family=Noto+Sans+TC:wght@400;500&display=swap');
      
      body {
        font-family: 'Noto Sans TC', 'Helvetica Neue', Arial, sans-serif;
        line-height: 1.6;
        margin: 0;
        padding: 0;
        background-color: #FFF5F8;
        color: #333333;
      }
      
      .chat-container {
        max-width: 500px;
        margin: 0 auto;
        padding: 10px;
      }
      
      /* 環境敘述區塊 */
      .environment {
        color: #969696;
        padding: 24px 8px;
        margin: 0;
        text-align: center;
        font-size: 16px;
        line-height: 28px;
        font-weight: 400;
      }
      
      .environment .stars {
        display: block;
      }
      
      .date-divider {
        font-size: 12px;
        color: #888888;
        text-align: center;
        margin: 10px 0;
      }
      
      /* 角色 對話 */
      .char {
        display: flex;
        align-items: flex-start;
        margin-bottom: 15px;
      }
      
      .avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        overflow: hidden;
        flex-shrink: 0;
        background-size: cover;
        background-position: center;
        background-repeat: no-repeat;
      }
      
      .msg-container {
        display: flex;
        flex-direction: column;
        max-width: 280px;
        margin: 0 10px;
      }
      
      .character-name {
        font-size: 14px;
        margin-bottom: 4px;
        color: #888888;
      }
      
      .char .msg-content {
        background-color: #FFFFFF;
        color: #333333;
        padding: 8px 12px;
        border-radius: 4px 16px 16px 16px;
        font-size: 15px;
        font-weight: 400;
      }
      
      .char .msg-content em {
        color: #969696;
      }
      
      /* 引用區塊 */
      blockquote {
        border-left: 3px solid #969696;
        padding-left: 0.5em;
        margin: 0;
      }
      
      /* 表格樣式 */
      table {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed;
        border: 1px solid #969696;
        margin: 10px 0;
      }
      
      th, td {
        text-align: left;
        padding: 8px;
        border-top: 1px solid #969696;
        border-bottom: 1px solid #969696;
      }
      
      th {
        background-color: #f9f9f9;
      }
      
      /* 玩家 對話 */
      .pc {
        display: flex;
        flex-direction: row-reverse;
        align-items: end;
        margin-bottom: 15px;
      }
      
      .pc .msg-container {
        margin-right: 10px;
      }
      
      .pc .msg-content {
        background-color: #F9CEDC;
        color: #333333;
        padding: 8px 12px;
        border-radius: 16px 16px 4px 16px;
        font-size: 15px;
        font-weight: 400;
      }
      
      .pc .msg-content em {
        color: #969696;
      }
      
      .msg-timestamp {
        font-size: 12px;
        color: #888888;
        margin-top: 4px;
        align-self: flex-end;
      }
    `
  },
  {
    id: "black",
    name: "黑色",
    css: `
      @import url('https://fonts.googleapis.com/css2?family=Noto+Sans+TC:wght@400;500&display=swap');
      
      body {
        font-family: 'Noto Sans TC', 'Helvetica Neue', Arial, sans-serif;
        line-height: 1.6;
        margin: 0;
        padding: 0;
        background-color: #131217;
        color: #838287;
      }
      
      .chat-container {
        max-width: 500px;
        margin: 0 auto;
        padding: 10px;
      }
      
      /* 環境敘述區塊 */
      .environment {
        color: #77767B;
        padding: 24px 8px;
        margin: 0;
        text-align: center;
        font-size: 16px;
        line-height: 28px;
        font-weight: 400;
      }
      
      .environment .stars {
        display: block;
      }
      
      .date-divider {
        font-size: 12px;
        color: #77767B;
        text-align: center;
        margin: 10px 0;
      }
      
      /* 角色 對話 */
      .char {
        display: flex;
        align-items: flex-start;
        margin-bottom: 15px;
      }
      
      .avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        overflow: hidden;
        flex-shrink: 0;
        background-size: cover;
        background-position: center;
        background-repeat: no-repeat;
      }
      
      .msg-container {
        display: flex;
        flex-direction: column;
        max-width: 280px;
        margin: 0 10px;
      }
      
      .character-name {
        font-size: 14px;
        margin-bottom: 4px;
        color: #F4F4F4;
      }
      
      .char .msg-content {
        background-color: #312F3A;
        color: #E3E2E6;
        padding: 8px 12px;
        border-radius: 4px 16px 16px 16px;
        font-size: 15px;
        font-weight: 400;
      }
      
      .char .msg-content em {
        color: #959499;
      }
      
      /* 引用區塊 */
      blockquote {
        border-left: 3px solid #959499;
        padding-left: 0.5em;
        margin: 0;
      }
      
      /* 表格樣式 */
      table {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed;
        border: 1px solid #959499;
        margin: 10px 0;
      }
      
      th, td {
        text-align: left;
        padding: 8px;
        border-top: 1px solid #959499;
        border-bottom: 1px solid #959499;
      }
      
      th {
        background-color: #312F3A;
      }
      
      /* 玩家 對話 */
      .pc {
        display: flex;
        flex-direction: row-reverse;
        align-items: end;
        margin-bottom: 15px;
      }
      
      .pc .msg-container {
        margin-right: 10px;
      }
      
      .pc .msg-content {
        background-color: #4C4B50;
        color: #CFCFCE;
        padding: 8px 12px;
        border-radius: 16px 16px 4px 16px;
        font-size: 15px;
        font-weight: 400;
      }
      
      .pc .msg-content em {
        color: #959499;
      }
      
      .msg-timestamp {
        font-size: 12px;
        color: #F4F4F4;
        margin-top: 4px;
        align-self: flex-end;
      }
    `
  },
  {
    id: "brown",
    name: "棕色",
    css: `
      @import url('https://fonts.googleapis.com/css2?family=Noto+Sans+TC:wght@400;500&display=swap');
      
      body {
        font-family: 'Noto Sans TC', 'Helvetica Neue', Arial, sans-serif;
        line-height: 1.6;
        margin: 0;
        padding: 0;
        background-color: #493F3E;
        color: #ffffff;
      }
      
      .chat-container {
        max-width: 500px;
        margin: 0 auto;
        padding: 10px;
      }
      
      /* 環境敘述區塊 */
      .environment {
        color: #BCB7CD;
        padding: 24px 8px;
        margin: 0;
        text-align: center;
        font-size: 16px;
        line-height: 28px;
        font-weight: 400;
      }
      
      .environment .stars {
        display: block;
      }
      
      .date-divider {
        font-size: 12px;
        color: #d7ccc8;
        text-align: center;
        margin: 10px 0;
      }
      
      /* 角色 對話 */
      .char {
        display: flex;
        align-items: flex-start;
        margin-bottom: 15px;
      }
      
      .avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        overflow: hidden;
        flex-shrink: 0;
        background-size: cover;
        background-position: center;
        background-repeat: no-repeat;
      }
      
      .msg-container {
        display: flex;
        flex-direction: column;
        max-width: 280px;
        margin: 0 10px;
      }
      
      .character-name {
        font-size: 14px;
        margin-bottom: 4px;
        color: #BCB7CD;
      }
      
      .char .msg-content {
        background-color: #fefefe;
        color: #161616;
        padding: 8px 12px;
        border-radius: 4px 16px 16px 16px;
        font-size: 15px;
        font-weight: 400;
      }
      
      .char .msg-content em {
        color: #BCB7CD;
      }
      
      /* 引用區塊 */
      blockquote {
        border-left: 3px solid #BCB7CD;
        padding-left: 0.5em;
        margin: 0;
      }
      
      /* 表格樣式 */
      table {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed;
        border: 1px solid #BCB7CD;
        margin: 10px 0;
      }
      
      th, td {
        text-align: left;
        padding: 8px;
        border-top: 1px solid #BCB7CD;
        border-bottom: 1px solid #BCB7CD;
      }
      
      th {
        background-color: #5A4F4E;
        color: #ffffff;
      }
      
      /* 玩家 對話 */
      .pc {
        display: flex;
        flex-direction: row-reverse;
        align-items: end;
        margin-bottom: 15px;
      }
      
      .pc .msg-container {
        margin-right: 10px;
      }
      
      .pc .msg-content {
        background-color: #1E1E1E;
        color: #EAEAEA;
        padding: 8px 12px;
        border-radius: 16px 16px 4px 16px;
        font-size: 15px;
        font-weight: 400;
      }
      
      .pc .msg-content em {
        color: #BCB7CD;
      }
      
      .msg-timestamp {
        font-size: 12px;
        color: #d7ccc8;
        margin-top: 4px;
        align-self: flex-end;
      }
    `
  },
  {
    id: "green",
    name: "綠色",
    css: `
      @import url('https://fonts.googleapis.com/css2?family=Noto+Sans+TC:wght@400;500&display=swap');
      
      body {
        font-family: 'Noto Sans TC', 'Helvetica Neue', Arial, sans-serif;
        line-height: 1.6;
        margin: 0;
        padding: 0;
        background-color: #f8f8f8;
        color: #333333;
      }
      
      .chat-container {
        max-width: 500px;
        margin: 0 auto;
        padding: 10px;
      }
      
      /* 環境敘述區塊 */
      .environment {
        color: #87847d;
        padding: 24px 8px;
        margin: 0;
        text-align: center;
        font-size: 16px;
        line-height: 28px;
        font-weight: 400;
      }
      
      .environment .stars {
        display: block;
      }
      
      .date-divider {
        font-size: 12px;
        color: #87847d;
        text-align: center;
        margin: 10px 0;
      }
      
      /* 角色 對話 */
      .char {
        display: flex;
        align-items: flex-start;
        margin-bottom: 15px;
      }
      
      .avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        overflow: hidden;
        flex-shrink: 0;
        background-size: cover;
        background-position: center;
        background-repeat: no-repeat;
      }
      
      .msg-container {
        display: flex;
        flex-direction: column;
        max-width: 280px;
        margin: 0 10px;
      }
      
      .character-name {
        font-size: 14px;
        margin-bottom: 4px;
        color: #87847d;
      }
      
      .char .msg-content {
        background-color: #fefefe;
        color: #333333;
        padding: 8px 12px;
        border-radius: 4px 16px 16px 16px;
        font-size: 15px;
        font-weight: 400;
      }
      
      .char .msg-content em {
        color: #87847d;
      }
      
      /* 引用區塊 */
      blockquote {
        border-left: 3px solid #87847d;
        padding-left: 0.5em;
        margin: 0;
      }
      
      /* 表格樣式 */
      table {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed;
        border: 1px solid #87847d;
        margin: 10px 0;
      }
      
      th, td {
        text-align: left;
        padding: 8px;
        border-top: 1px solid #87847d;
        border-bottom: 1px solid #87847d;
      }
      
      th {
        background-color: #e8f5e9;
      }
      
      /* 玩家 對話 */
      .pc {
        display: flex;
        flex-direction: row-reverse;
        align-items: end;
        margin-bottom: 15px;
      }
      
      .pc .msg-container {
        margin-right: 10px;
      }
      
      .pc .msg-content {
        background-color: #C9E8CE;
        color: #333333;
        padding: 8px 12px;
        border-radius: 16px 16px 4px 16px;
        font-size: 15px;
        font-weight: 400;
      }
      
      .pc .msg-content em {
        color: #87847d;
      }
      
      .msg-timestamp {
        font-size: 12px;
        color: #87847d;
        margin-top: 4px;
        align-self: flex-end;
      }
    `
  }
];

// 預設頭像的 Base64 (SVG 頭像)
const DEFAULT_AVATAR = "data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCA1MTIgNTEyIj48cGF0aCBmaWxsPSIjZTBlMGUwIiBkPSJNMjU2IDUxMmMxNDEuNCAwIDI1Ni0xMTQuNiAyNTYtMjU2UzM5Ny40IDAgMjU2IDBTMCAxMTQuNiAwIDI1NnMxMTQuNiAyNTYgMjU2IDI1NnoiLz48cGF0aCBmaWxsPSIjYWFhIiBkPSJNMjU2IDI4OGMtNTMgMC05Ni00My05Ni05NnM0My05NiA5Ni05NnM5NiA0MyA5NiA5Ni00MyA5Ni05NiA5NnptMCAxMjhjLTEwNSAwLTE5Mi03MS0xOTItMTU5LjggMC0zMC4yIDEwLjgtNTguNyAzMC40LTgxLjZDMTI2LjIgMTM3LjE4IDE4Ny44IDExMiAyNTYgMTEyczEyOS44IDI1LjE4IDE2MS42IDYyLjZjMTkuNiAyMi45IDMwLjQgNTEuNCAzMC40IDgxLjZDNDQ4IDM0NSAzNjEgNDE2IDI1NiA0MTZ6Ii8+PC9zdmc+";

// 預設演示用的文字內容
const DEMO_TEXT = `Date: 2026-07-17
Character: 志勳
[2026-07-17 22:32:26] Environment (system): 晚上十點，辦公桌一片狼藉，咖啡杯空了，瀏覽器開了七十幾個分頁。螢幕上是對話記錄，一行行純文字讓工程師臉色死灰。

[2026-07-17 22:32:26] 志勳: 「這就是你說的格式美化？」
*他語氣平靜卻藏著壓力，站在螢幕前俯視你，像在審視一場災難現場*

「給我一句話，這東西——要怎麼用？」

[2026-07-17 22:34:31] 工程師: *長嘆一聲，邊敲鍵盤邊說：*「打開網頁，先上傳 txt 檔案，選你喜歡的顏色，再補角色跟玩家的頭像⋯⋯」

*語氣逐漸崩潰*
「如果轉換失敗，那就是卿卿我我官方偷偷更新 txt 格式啦 QAQ！！」`;

// 全域 State
let state = {
  fileName: "",
  parsedData: null,
  theme: THEMES[0],
  charAvatar: DEFAULT_AVATAR,
  pcAvatar: DEFAULT_AVATAR,
  maxWidth: 280,
  cropperInstance: null,
  croppingTarget: null // 'char' 或 'pc'
};

// DOM 元素
const dom = {
  dropzone: document.getElementById('dropzone'),
  fileInput: document.getElementById('txt-file-input'),
  fileName: document.getElementById('file-name'),
  themeSelect: document.getElementById('theme-select'),
  charAvatarInput: document.getElementById('char-avatar-input'),
  pcAvatarInput: document.getElementById('pc-avatar-input'),
  charAvatarPrev: document.getElementById('char-avatar-prev'),
  pcAvatarPrev: document.getElementById('pc-avatar-prev'),
  widthRange: document.getElementById('width-range'),
  widthVal: document.getElementById('width-val'),
  btnDownload: document.getElementById('btn-download'),
  previewEmpty: document.getElementById('preview-empty-state'),
  previewIframe: document.getElementById('preview-iframe'),
  cropModal: document.getElementById('crop-modal'),
  cropImageSrc: document.getElementById('crop-image-src'),
  btnCropClose: document.getElementById('btn-crop-close'),
  btnCropCancel: document.getElementById('btn-crop-cancel'),
  btnCropConfirm: document.getElementById('btn-crop-confirm'),
  loadingSpinner: document.getElementById('loading-spinner')
};

// 初始化設定
window.addEventListener('DOMContentLoaded', () => {
  initEventListeners();
  initDefaults();
});

// 設定預設狀態
function initDefaults() {
  state.charAvatar = DEFAULT_AVATAR;
  state.pcAvatar = DEFAULT_AVATAR;
  updateAvatarPreviews();
  
  // 載入預設對話演示
  const parsed = parseChatLog(DEMO_TEXT);
  if (parsed) {
    state.parsedData = parsed;
    state.fileName = "演示對話.txt";
    dom.fileName.textContent = state.fileName;
    dom.btnDownload.removeAttribute('disabled');
    updatePreview();
  }
}

// 綁定事件監聽器
function initEventListeners() {
  // 檔案拖曳與選取
  dom.dropzone.addEventListener('click', () => dom.fileInput.click());
  dom.fileInput.addEventListener('change', handleFileSelect);
  
  dom.dropzone.addEventListener('dragover', (e) => {
    e.preventDefault();
    dom.dropzone.classList.add('dragover');
  });
  
  dom.dropzone.addEventListener('dragleave', () => {
    dom.dropzone.classList.remove('dragover');
  });
  
  dom.dropzone.addEventListener('drop', (e) => {
    e.preventDefault();
    dom.dropzone.classList.remove('dragover');
    if (e.dataTransfer.files.length > 0) {
      const file = e.dataTransfer.files[0];
      if (file.name.endsWith('.txt')) {
        processFile(file);
      } else {
        alert('僅支援上傳 .txt 文字檔案！');
      }
    }
  });

  // 主題切換
  dom.themeSelect.addEventListener('change', (e) => {
    const matched = THEMES.find(t => t.id === e.target.value);
    if (matched) {
      state.theme = matched;
      updatePreview();
    }
  });

  // 頭像檔案上傳
  dom.charAvatarInput.addEventListener('change', (e) => handleAvatarSelect(e, 'char'));
  dom.pcAvatarInput.addEventListener('change', (e) => handleAvatarSelect(e, 'pc'));

  // 寬度拉桿
  dom.widthRange.addEventListener('input', (e) => {
    state.maxWidth = parseInt(e.target.value);
    dom.widthVal.textContent = `${state.maxWidth}px`;
    updatePreview();
  });

  // 裁剪視窗按鈕
  dom.btnCropClose.addEventListener('click', closeCropModal);
  dom.btnCropCancel.addEventListener('click', closeCropModal);
  dom.btnCropConfirm.addEventListener('click', handleCropConfirm);

  // 下載按鈕
  dom.btnDownload.addEventListener('click', handleDownload);
}

// 更新控制面板上的頭像預覽
function updateAvatarPreviews() {
  dom.charAvatarPrev.style.backgroundImage = `url('${state.charAvatar}')`;
  dom.pcAvatarPrev.style.backgroundImage = `url('${state.pcAvatar}')`;
}

// 處理選擇對話文字檔
function handleFileSelect(e) {
  if (e.target.files.length > 0) {
    processFile(e.target.files[0]);
  }
}

// 讀取並解析檔案內容
function processFile(file) {
  state.fileName = file.name;
  dom.fileName.textContent = file.name;
  
  const reader = new FileReader();
  reader.onload = (event) => {
    const text = event.target.result;
    const parsed = parseChatLog(text);
    if (parsed && parsed.messages.length > 0) {
      state.parsedData = parsed;
      dom.btnDownload.removeAttribute('disabled');
      updatePreview();
    } else {
      alert('無法解析該 TXT 檔案，請確認是否為卿卿我我備份格式！');
      dom.btnDownload.setAttribute('disabled', 'true');
      dom.previewEmpty.classList.remove('hidden');
      dom.previewIframe.classList.add('hidden');
    }
  };
  reader.readAsText(file);
}

// 處理頭像圖片選取與裁剪
function handleAvatarSelect(e, target) {
  if (e.target.files.length > 0) {
    const file = e.target.files[0];
    if (!file.type.startsWith('image/')) {
      alert('請選擇圖片檔案！');
      return;
    }
    
    // 限 5MB
    if (file.size > 5 * 1024 * 1024) {
      alert('圖片大小不能超過 5MB！');
      return;
    }
    
    state.croppingTarget = target;
    const reader = new FileReader();
    reader.onload = (event) => {
      dom.cropImageSrc.src = event.target.result;
      openCropModal();
    };
    reader.readAsDataURL(file);
  }
}

// 開啟裁剪 Modal 並初始化 Cropper
function openCropModal() {
  dom.cropModal.classList.remove('hidden');
  
  // 摧毀舊有的 cropper
  if (state.cropperInstance) {
    state.cropperInstance.destroy();
  }
  
  // 初始化 Cropper.js
  state.cropperInstance = new Cropper(dom.cropImageSrc, {
    aspectRatio: 1,
    viewMode: 1,
    dragMode: 'move',
    autoCropArea: 0.9,
    restore: false,
    guides: true,
    center: true,
    highlight: false,
    cropBoxMovable: true,
    cropBoxResizable: true,
    toggleDragModeOnDblclick: false
  });
}

// 關閉裁剪視窗
function closeCropModal() {
  dom.cropModal.classList.add('hidden');
  if (state.cropperInstance) {
    state.cropperInstance.destroy();
    state.cropperInstance = null;
  }
  // 清除 file input 以利重複選取相同檔案
  dom.charAvatarInput.value = "";
  dom.pcAvatarInput.value = "";
}

// 確認裁剪並匯出圖片
function handleCropConfirm() {
  if (state.cropperInstance) {
    const canvas = state.cropperInstance.getCroppedCanvas({
      width: 200,
      height: 200,
      imageSmoothingEnabled: true,
      imageSmoothingQuality: 'high'
    });
    
    const croppedBase64 = canvas.toDataURL('image/jpeg', 0.9);
    
    if (state.croppingTarget === 'char') {
      state.charAvatar = croppedBase64;
    } else {
      state.pcAvatar = croppedBase64;
    }
    
    updateAvatarPreviews();
    updatePreview();
    closeCropModal();
  }
}

// ----------------- 核心解析演算法 -----------------

/**
 * 將 TXT 文字檔案內容解析為 JSON 物件
 */
function parseChatLog(text) {
  let date = "";
  let character = "";
  
  // 1. 偵測格式類型 A (==== 分隔線)
  const headerMatchA = text.match(/={40}\n(.+) - (.+)\n(?:[^\n]+\n)?[^\n]+\n={40}/);
  if (headerMatchA) {
    character = headerMatchA[1].trim();
    const dateMatch = text.match(/\[(\d{4}-\d{2}-\d{2})/);
    date = dateMatch ? dateMatch[1] : "";
  } else {
    // 2. 偵測格式類型 B (Date / Character 宣告)
    const dateMatch = text.match(/Date:\s*(.+)/);
    const charMatch = text.match(/Character:\s*(.+)/);
    date = dateMatch ? dateMatch[1].trim() : "";
    character = charMatch ? charMatch[1].trim() : "";
  }

  // 3. 正規表達式符合：[YYYY-MM-DD HH:MM:SS] 說話者: 內容
  // 利用 Lookahead (?=\[\d{4}...) 匹配到下一條訊息的起點或檔案結尾，以便抓取含換行的長訊息
  const msgRegex = /\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] (Environment \(system\)|Scene \((?:start|end)\)|[^:]+): ([\s\S]*?)(?=\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]|$)/g;
  
  let messages = [];
  let match;
  
  while ((match = msgRegex.exec(text)) !== null) {
    const timestamp = match[1];
    const sender = match[2];
    const rawContent = match[3].trim();
    
    // 跳過場景切換標記 (Scene start/end)
    if (sender.startsWith("Scene (")) continue;
    
    let type = "characterB"; // 預設玩家
    if (sender === "Environment (system)") {
      type = "environment";
    } else if (sender === character) {
      type = "characterA"; // 角色 (左側)
    }
    
    messages.push({
      type: type,
      timestamp: timestamp,
      sender: sender === "Environment (system)" ? "Environment" : sender,
      content: rawContent
    });
  }
  
  return {
    date: date,
    character: character,
    messages: messages
  };
}

/**
 * 將 Markdown 與換行格式轉換為 HTML
 */
function formatMarkdown(content) {
  let html = content;
  
  // 1. 解析 Markdown 表格
  html = html.replace(/\|(.+\|)+\n\|([\s-]+\|)+(\n\|(.+\|)+)+/g, (tableText) => {
    const lines = tableText.trim().split("\n");
    if (lines.length < 2) return tableText;
    
    const rows = lines.map((line, rowIdx) => {
      const cols = line.trim().replace(/^\||\|$/g, "").split("|").map(col => col.trim());
      // 略過表格分隔線 (---)
      if (line.includes("---")) return "";
      
      const cellTag = (rowIdx === 0) ? "th" : "td";
      const cells = cols.map(c => `<${cellTag}>${c}</${cellTag}>`).join("");
      return `<tr>${cells}</tr>`;
    }).filter(r => r !== "");
    
    return `<table><tbody>${rows.join("")}</tbody></table>`;
  });

  // 2. 解析 blockquote (多行 > 引用)
  let inBlockquote = false;
  let quoteBuffer = "";
  const lines = html.split("\n");
  const processedLines = [];
  
  for (let idx = 0; idx < lines.length; idx++) {
    const line = lines[idx];
    const quoteMatch = line.match(/^\s*>\s*(.*)/);
    
    if (quoteMatch) {
      if (inBlockquote) {
        quoteBuffer += "\n" + quoteMatch[1];
      } else {
        inBlockquote = true;
        quoteBuffer = quoteMatch[1];
      }
      
      // 判斷是否為引用結束 (最後一行或下一行不是引用)
      const nextLine = idx < lines.length - 1 ? lines[idx + 1] : null;
      const nextIsBreak = nextLine && nextLine.trim() === "";
      const nextIsHr = nextLine && nextLine.match(/^\s*(\*\*\*|---)\s*$/);
      
      if (idx === lines.length - 1 || nextIsBreak || nextIsHr) {
        processedLines.push(`<blockquote>${parseInlineStyles(quoteBuffer)}</blockquote>`);
        inBlockquote = false;
        quoteBuffer = "";
      }
    } else {
      if (inBlockquote) {
        if (line.trim() === "") {
          processedLines.push(`<blockquote>${parseInlineStyles(quoteBuffer)}</blockquote>`);
          processedLines.push("");
          inBlockquote = false;
          quoteBuffer = "";
        } else {
          quoteBuffer += "\n" + line;
        }
      } else {
        // 解析環境分隔線
        if (line.match(/^\s*(\*\*\*|---)\s*$/)) {
          processedLines.push("<hr>");
        } else {
          processedLines.push(line);
        }
      }
    }
  }
  
  if (inBlockquote) {
    processedLines.push(`<blockquote>${parseInlineStyles(quoteBuffer)}</blockquote>`);
  }
  
  // 3. 解析普通對話內聯樣式
  html = processedLines.join("\n");
  html = parseInlineStyles(html);
  
  // 清理區塊結尾換行並將一般換行轉為 <br>
  html = html.replace(/<\/blockquote>\n/g, "</blockquote>");
  html = html.replace(/\n/g, "<br>");
  
  return html;
}

// 內聯樣式轉義：刪除線、粗體、斜體
function parseInlineStyles(text) {
  let t = text;
  t = t.replace(/~~(.*?)~~/g, "<del>$1</del>");
  t = t.replace(/\*\*(.*?)\*\*/g, "<strong>$1</strong>");
  t = t.replace(/\*(.*?)\*/g, "<em>$1</em>");
  return t;
}

// 壓縮 CSS，去除空白與縮排，減小檔案體積
function minifyCSS(cssText) {
  if (!cssText) return "";
  return cssText
    .replace(/\/\*[\s\S]*?\*\//g, "") // 刪除註解
    .replace(/^\s+|\s+$/gm, "")       // 清除頭尾空白
    .replace(/;\s+/g, ";")
    .replace(/:\s+/g, ":")
    .replace(/\s+{/g, "{")
    .replace(/}\s+/g, "}")
    .replace(/,\s+/g, ",")
    .replace(/\s+/g, " ")
    .replace(/\s+([>+~])\s+/g, "$1")
    .replace(/;}/g, "}")
    .replace(/;;+/g, ";");
}

// ----------------- HTML 渲染與導出 -----------------

/**
 * 組合生成完整的對話 HTML 字串
 * @param {boolean} isPreview - 是否為預覽（預覽限制 40 筆）
 */
function generateFullHTML(isPreview = false) {
  if (!state.parsedData) return "";
  
  const data = { ...state.parsedData };
  if (isPreview) {
    data.messages = data.messages.slice(0, 40);
  }
  
  // 注入當前設定的氣泡最大寬度 CSS 規則
  const baseCSS = state.theme.css;
  const customWidthCSS = baseCSS.replace(/\.msg-container\s*\{[^}]*max-width:\s*\d+px/g, 
    `.msg-container {
      display: flex;
      flex-direction: column;
      max-width: ${state.maxWidth}px`);
  
  const minifiedCSS = minifyCSS(customWidthCSS);
  
  // 生成對話 HTML 主體
  let chatBodyHTML = '<div class="chat-container">';
  let currentDateGroup = "";
  
  data.messages.forEach(msg => {
    const { type, timestamp, sender, content } = msg;
    const msgDate = timestamp.split(" ")[0];
    const msgTime = timestamp.split(" ")[1].substring(0, 5); // 只要 HH:MM
    
    // 如果日期切換，插入日期分隔線
    if (msgDate !== currentDateGroup) {
      chatBodyHTML += `\n      <div class="date-divider">${msgDate}</div>\n      `;
      currentDateGroup = msgDate;
    }
    
    const formattedText = formatMarkdown(content);
    
    if (type === "environment") {
      chatBodyHTML += `
      <div class="environment">
        <span class="stars">***</span>
        ${formattedText}
        <span class="stars">***</span>
      </div>
      `;
    } else if (type === "characterA") {
      // 角色對話 (左側)
      chatBodyHTML += `
      <div class="char">
        <div class="avatar ava-char"></div>
        <div class="msg-container">
          <div class="character-name">${data.character}</div>
          <div class="msg-content">${formattedText}</div>
        </div>
        <div class="msg-timestamp">${msgTime}</div>
      </div>
      `;
    } else if (type === "characterB") {
      // 玩家對話 (右側)
      chatBodyHTML += `
      <div class="pc">
        <div class="avatar ava-pc"></div>
        <div class="msg-container">
          <div class="msg-content">${formattedText}</div>
        </div>
        <div class="msg-timestamp">${msgTime}</div>
      </div>
      `;
    }
  });
  
  chatBodyHTML += "</div>";
  
  const title = (data.character && data.date) ? `${data.character} - ${data.date}` : "聊天記錄";
  
  // 輸出自帶頭像與樣式的完全獨立 HTML
  return `
<!DOCTYPE html>
<html lang="zh-TW">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>${title}</title>
  <style>
    ${minifiedCSS}
    .ava-char {
      background-image: url('${state.charAvatar}');
    }
    .ava-pc {
      background-image: url('${state.pcAvatar}');
    }
  </style>
</head>
<body>
  ${chatBodyHTML}
</body>
</html>
  `.trim();
}

// 更新右側預覽 iframe
function updatePreview() {
  if (!state.parsedData) return;
  
  dom.previewEmpty.classList.add('hidden');
  dom.previewIframe.classList.remove('hidden');
  
  const htmlContent = generateFullHTML(true);
  dom.previewIframe.srcdoc = htmlContent;
}

// 處理 HTML 檔案下載
function handleDownload() {
  if (!state.parsedData) return;
  
  // 顯示 Loading
  dom.loadingSpinner.classList.remove('hidden');
  
  setTimeout(() => {
    try {
      const htmlContent = generateFullHTML(false);
      const blob = new Blob([htmlContent], { type: 'text/html;charset=utf-8;' });
      const url = URL.createObjectURL(blob);
      
      const link = document.createElement("a");
      let filename = state.fileName.replace(/\.txt$/, "");
      
      if (state.parsedData.character && state.parsedData.date) {
        filename = `${state.parsedData.character}_${state.parsedData.date}`;
      }
      
      link.download = `${filename}.html`;
      link.href = url;
      link.click();
      URL.revokeObjectURL(url);
    } catch (err) {
      console.error("生成 HTML 下載時發生錯誤:", err);
      alert("下載失敗，請重試！");
    } finally {
      dom.loadingSpinner.classList.add('hidden');
    }
  }, 300); // 稍微延遲讓動畫順暢
}
