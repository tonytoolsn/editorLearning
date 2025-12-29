import 'bootstrap/dist/js/bootstrap.bundle.min';
import 'bootstrap/dist/css/bootstrap.min.css';
import 'summernote/dist/summernote-bs5.min';
import 'summernote/dist/summernote-bs5.min.css';
import 'summernote/dist/lang/summernote-zh-TW.js';
import '../styles/summernote.css';
import { APP_URL } from './config.js';

/**
 * SummernoteManager 編輯器管理類別
 *
 * 設計目標：
 * - 提高代碼重用性
 * - 簡化初始化過程
 * - 易於維護和管理 Summernote 編輯器實例
 * - 可擴展性強，方便未來添加更多自定義功能
 * - 支援多種初始化場景
 * - 支援 document / 指定容器 / modal 等範圍初始化
 * - 支援自定義配置覆寫
 *
 * 自動初始化頁面上所有 .summernote
 * 自定義初始化 <div className="custom-summernote"></div>
 * 支援快速初始化（static initInstance)，建議統一使用 SummernoteManager.initInstance()
 *
 * 可傳入 root 參數指定初始化範圍
 * 可傳入 config 參數覆寫預設設定
 * 可傳入 activeClass 參數指定已初始化標記的 class 名稱
 * 可傳入 autoInit 參數決定是否自動初始化
 * 提供 onChange 事件處理函式，可在子類別中覆寫
 * 提供 customHrButton 方法，作為自定義按鈕範例
 * 頁面加載完成後自動初始化
 * 可用於進階 / 手動控制場景
 * 可擴展性強，方便未來添加更多自定義功能
 */
export default class SummernoteManager {

    /**
     * 預設用於標記「已初始化」的 class
     * 用來防止同一元素被重複初始化
     */
    static DEFAULT_ACTIVE_CLASS = 'is-summernote-active';

    /** 支援的 callbacks（明確列出，避免錯誤） */
    static SUPPORTED_CALLBACKS = [
        // 'onInit',
        'onChange',
        'onImageUpload',
        'onMediaDelete',
        // 'onPaste',
        // 'onKeydown',
        // 'onFocus',
        // 'onBlur'
    ];

    static ATTR_MAP = {
        imageUploadUrl: {
            attr: 'data-image-upload-url',
            fallback: '/api/editor/images',
        },
        imageDeleteUrl: {
            attr: 'data-image-delete-url',
            fallback: '/api/editor/images'
        },
        profile: {
            attr: 'data-profile',
            fallback: null
        },
    };

    /**
     * @param {Object} options
     * @param {HTMLElement|jQuery|string} options.root
     * 初始化的根節點，預設 document
     * @param {Object} options.config
     * Summernote 設定（會 merge 到 defaultConfig）
     * @param {string} options.activeClass
     * 用於標記已初始化的 class
     * @param {boolean} options.autoInit
     * 是否在 constructor 中立即初始化
     */
    constructor({
        root = document,
        config  = {},
        activeClass = SummernoteManager.DEFAULT_ACTIVE_CLASS,
        autoInit = true
    } = {}) {
        this.$root = $(root);
        this.userConfig  = config;
        this.activeClass = activeClass;
        const self = this;

        // 只合併一次
        this.baseConfig = $.extend(true, {}, this.defaultConfig, this.userConfig);

        // 是否在建立實例時立即初始化
        if (autoInit) {
            this.init();
        }
    }

    /**
     * 取得需要初始化的目標元素
     *
     * - 當 root 是 document：找 .summernote
     * - 當 root 是容器：尋找 .custom-summernote
     * - 若 root 本身就是目標元素，則直接使用
     */
    get targets() {
        const isDocument = this.$root[0] === document;
        const targetClass = isDocument ? 'summernote' : 'custom-summernote';

        return this.$root.hasClass(targetClass)
            ? this.$root
            : this.$root.find(`.${targetClass}`);
    }

    /* ============================
     * 內建 callbacks
     * ============================ */

    /**
     * Summernote onChange 回調
     *
     * 用於：
     * - 為 blockquote / pre 自動加上自訂樣式 class
     * - 保證編輯內容結構統一
     */
    onChange(textarea, contents, $editable) {
        const $editor = $editable.closest('.note-editor');;
        const classMap = {
            BLOCKQUOTE: 'note-custom-blockquote',
            PRE: 'note-custom-pre'
        };

        $editor
            .find('blockquote:not(.note-custom-blockquote), pre:not(.note-custom-pre)')
            .each((_, el) => {
                const className = classMap[el.tagName];
                if (className) el.classList.add(className);
            });
    }

    getAttr($el, key) {
        const { attr, fallback } = SummernoteManager.ATTR_MAP[key] || {};
        return $el.attr(attr) ?? fallback;
    }

    // 上傳圖片到後端
    uploadImage(textarea, file) {
        const $textarea = $(textarea);

        let uploadUrl = this.getAttr($textarea,'imageUploadUrl');
        const profile = this.getAttr($textarea, 'profile');

        if (!uploadUrl) {
            console.error('缺少 imageUploadUrl 屬性');
            return;
        }

        if (!profile) {
            console.error('缺少 profile 屬性。');
            return;
        }

        const data = new FormData();
        data.append('file', file);
        data.append('profile', profile);

        $.ajax({
            url: APP_URL + uploadUrl,
            method: 'POST',
            data: data,
            processData: false,
            contentType: false,
            success: function(res) {
                console.log('Image uploaded successfully:', res);
                $textarea.summernote('insertImage', res.url, function ($image) {
                    $image.attr('data-uuid', res.uuid);
                });
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.log(jqXHR);
                console.error('Image upload failed:', textStatus, errorThrown);
                // alert(jqXHR.responseJSON?.error || '上傳失敗');
            }
        });
    }

    // 刪除圖片
    deleteImage(textarea, uuid) {
        const $textarea = $(textarea);
        let deleteUrl = this.getAttr($textarea,'imageDeleteUrl');

        $.ajax({
            url: APP_URL + deleteUrl + '/' + uuid,
            type: "DELETE",
            success: function(res) {
                console.log("刪除成功", res);
                console.log("標記刪除成功", res);
            },
            error: function(err) {
                console.error(err);
                alert(err.responseJSON?.error || '刪除失敗');
            }
        });
    }

    onImageUpload(textarea, files) {
        for(let i=0; i<files.length; i++) {
          this.uploadImage(textarea, files[i]);
        }
    }

    onMediaDelete(textarea, target) {
        const $img = $(target[0]);
        const uuid = $img.data('uuid');
        deleteImage(textarea, uuid);
    }

    /* ============================
     * Callback 合成器
     * ============================ */

    /**
     * 通用 callback 合成器
     *
     * @param {Function} internal 內建 callback
     * @param {Function} user 使用者 callback
     * @param {Function} override 完全覆寫 callback
     */
    composeCallback({ internal, user, override }) {
        // 為了可以 拿到 manager 跟 textarea dom 使用 call
        const manager = this;

        if (typeof override === 'function') {
            return function (...args) {
                const textarea = this;
                override.call(manager, textarea, ...args)
            };
        }

        if (typeof internal === 'function' && typeof user === 'function') {
            return function (...args) {
                const textarea = this;
                internal.call(manager, textarea, ...args);
                user.call(manager, textarea, ...args);
            };
        }

        return function(...args) {
            const textarea = this; // editor div
            if (typeof internal === 'function') internal.call(manager, textarea, ...args);
            if (typeof user === 'function') user.call(manager, textarea, ...args);
        };
    }

    /**
     *  建立最終 callbacks 設定
     */
    buildCallbacks() {
        const userCallbacks = this.userConfig.callbacks || {};
        const overrideCallbacks = this.userConfig.overrideCallbacks || {};
        const callbacks = {};

        SummernoteManager.SUPPORTED_CALLBACKS.forEach((name) => {
            callbacks[name] = this.composeCallback({
                internal: this[name],
                user: userCallbacks[name],
                override: overrideCallbacks[name]
            });
        });

        return callbacks;
    }

    /* ============================
     * 內建 Buttons
     * ============================ */

    /**
     * 自訂「虛線 HR」按鈕
     *
     * 插入：
     * <hr class="note-custom-dash-hr">
     */
    customDashHrButton(context) {
        const ui = $.summernote.ui;
        return ui.button({
            contents: '<span class="note-icon-dash-btn"></span>',
            tooltip: '虛線',
            click() {
                const node = $('<hr class="note-custom-dash-hr">')[0];
                context.invoke('editor.insertNode', node);
            }
        }).render();
    }

    /** ===========================
     * Buttons 合成器
     * =========================== */

    /**
     * 建立最終的 Summernote 按鈕設置
     *
     * 整合三種按鈕來源：
     * 1️. 內置按鈕（internalButtons）: 框架自帶的按鈕
     * 2️. 使用者按鈕（userButtons）: 用戶透過 config.buttons 附加的按鈕
     * 3️. 覆蓋按鈕（overrideButtons）: 用戶透過 config.overrideButtons 完全覆蓋內置或附加按鈕
     *
     * 合併邏輯：
     * - 如果 overrideButtons 存在同名按鈕 → 使用它（完全覆蓋）
     * - 否則，若 userButtons 存在同名按鈕 → 使用它（附加 / 覆蓋內置）
     * - 否則 → 使用內建按鈕
     */
    buildButtons() {
        // 1. 內建按鈕
        const internalButtons = {
            customDashHr: this.customDashHrButton
        };

        // 2. 使用者按鈕 & 覆蓋按鈕
        const userButtons = this.userConfig.buttons || {};

        // 3. 覆蓋按鈕
        const overrideButtons = this.userConfig.overrideButtons || {};

        // 合併按鈕 key，遍歷決定最終按鈕函數
        return Object.keys({ ...internalButtons, ...userButtons, ...overrideButtons })
            .reduce((acc, key) => {
                // 合併邏輯：
                // override > user > internal
                acc[key] = overrideButtons[key] || userButtons[key] || internalButtons[key];
                return acc;
            }, {});
    }

    /** ===========================
     * 預設設定
     * =========================== */

    /**
     * Summernote 預設設定
     *
     * 不要直接修改 defaultConfig
     * 請使用 userConfig 傳入自訂設定覆寫
     * 所有通用行為、toolbar、callback 都集中在這裡
     */
    get defaultConfig() {
        return {
            height: 500,
            lang: 'zh-TW',
            disableDragAndDrop: true,
            shortcuts: false,
            dialogsInBody: true,

            toolbar: [
                ['misc', ['undo','redo']],
                ['style', ['style']],
                ['customPre', ['customPre']],
                ['fontstyle', ['bold','italic','underline','strikethrough']],
                ['fontsize', ['fontsize', 'fontname']],
                ['forecolor', ['forecolor','backcolor']],
                ['superscript', ['superscript','subscript','clear']],
                ['paragraph', ['paragraph','height']],
                ['para', ['ol','ul']],
                ['insert', ['link','picture','video','table','hr','customDashHr']],
                ['view', ['fullscreen','codeview']]
            ],

            fontNames: [
                'Arial',
                'Arial Black',
                'Comic Sans MS',
                'Courier New',
                'Helvetica',
                'Tahoma',
                'Times New Roman',
                'Verdana',
                '微軟正黑體'
            ],

            fontNamesIgnoreCheck: [
                '微軟正黑體',
            ],
        };
    }

    /*
     * 取得預設配置
     */
    getPresetConfig(name) {
        const presets = {
            simple: {
                toolbar: [
                    ['fontstyle', ['bold', 'italic']],
                    ['para', ['ul', 'ol']],
                    ['insert', ['link']]
                ]
            },
            full: {
                toolbar: this.defaultConfig.toolbar
            }
        };

        return presets[name] || {};
    }

    /*
     * 取得預設配置
     */
    getConfigFromData($el) {
        const config = {};

        const preset = $el.data('preset');
        if (preset) {
            Object.assign(config, this.getPresetConfig(preset));
        }

        const height = $el.data('height');
        if (height !== undefined) {
            config.height = Number(height);
        }

        return config;
    }

    /**
     * 初始化 Summernote
     *
     * - 自動跳過已初始化的元素
     * - 支援重複呼叫（安全性）
     */
    init() {
        this.targets.each((_, el) => {
            const $el = $(el);

            // 防止重複初始化
            if ($el.hasClass(this.activeClass)) return;

            const dataConfig = this.getConfigFromData($el);
            const instanceConfig = {
                ...this.baseConfig,
                ...dataConfig,
                callbacks: this.buildCallbacks(),
                buttons: this.buildButtons()
            };

            $el.summernote(instanceConfig);
            $el.addClass(this.activeClass);
        });
    }

    /**
     * 靜態快速初始化方法（建議使用）
     *
     * 用法：
     * SummernoteManager.init();
     * SummernoteManager.init({ root: '#modal' });
     */
    static initInstance(options = {}) {
        return new SummernoteManager({
            ...options,
            autoInit: true
        });
    }
}

// 創建一個全域物件，用作專案的命名空間：
// 可以把專案所有全域變數統一管理到 wfs 下
// 避免污染全域命名空間
// 不會跟其他套件的 SummernoteManager 衝突
window.wfs = window.wfs || {};
wfs.SummernoteManager = SummernoteManager;

/* 頁面載入自動初始化 */
$(function () {
    // 使用命名空間掛載 SummernoteManager
    wfs.SummernoteManager.initInstance();
});

/**
 * ============================================================
 * SummernoteManager 使用範例
 * ============================================================
 *
 * 本文件示範常見的 Summernote 初始化使用情境：
 * - 頁面自動初始化
 * - Modal / 指定容器初始化
 * - 單一元素初始化
 * - Callback 合成 / 覆寫
 * - data-* 屬性動態設定
 */

/* ------------------------------------------------------------
 * 範例 1：頁面載入時自動初始化（最常見）
 * ------------------------------------------------------------
 *
 * 自動初始化頁面上所有 `.summernote`
 *
 * HTML：
 * <textarea id="textarea1" class="summernote"></textarea>
 * <textarea id="textarea2" class="summernote"></textarea>
 *
 * JS：
 * wfs.SummernoteManager.initInstance();
 */

/* ------------------------------------------------------------
 * 範例 2：手動控制（Modal 內初始化）
 * ------------------------------------------------------------
 *
 * 初始化 #modal_name 內的 `.custom-summernote`
 * 適合用於 Bootstrap Modal / 動態載入內容
 *
 * HTML：
 * <div id="modal_name" class="modal" tabindex="-1" role="dialog">
 *     ...
 *     <textarea class="custom-summernote"></textarea>
 *     ...
 * </div>
 *
 * JS：
 * const summernoteManager = wfs.SummernoteManager.initInstance({
 *     root: '#modal_name',
 *     config: {
 *         height: 400
 *     },
 *     activeClass: 'my-summernote-active',
 *     autoInit: false   // 關閉自動初始化
 * });
 *
 * // 在 modal show 時手動呼叫
 * summernoteManager.init();
 */

/* ------------------------------------------------------------
 * 範例 3：單一元素初始化
 * ------------------------------------------------------------
 *
 * 直接初始化某一個 textarea
 *
 * HTML：
 * <textarea id="id_name" class="custom-summernote"></textarea>
 *
 * JS：
 * wfs.SummernoteManager.initInstance({
 *     root: '#id_name',
 *     config: {
 *         height: 300
 *     }
 * });
 */

/* ------------------------------------------------------------
 * 範例 4：保留內建 callback，並追加 user callback
 * ------------------------------------------------------------
 *
 * internal onChange + user onChange 會「依序執行」
 *
 * JS：
 * wfs.SummernoteManager.initInstance({
 *     config: {
 *         height: 500,
 *         callbacks: {
 *             onChange(contents) {
 *                 console.log('user onChange');
 *             }
 *         }
 *     }
 * });
 */

/* ------------------------------------------------------------
 * 範例 5：完全覆寫 callback（override）
 * ------------------------------------------------------------
 *
 * overrideCallbacks 會「完全取代」內建與 user callback
 *
 * JS：
 * wfs.SummernoteManager.initInstance({
 *     config: {
 *         height: 500,
 *         overrideCallbacks: {
 *             onChange(textarea, contents) {
 *                 console.log('override onChange');
 *                 console.log(this);   // manager
 *                 console.log(textarea);
 *                 console.log(contents);
 *             }
 *         }
 *     }
 * });
 */

/* ------------------------------------------------------------
 * 範例 6：使用 data-* 屬性設定單一實例
 * ------------------------------------------------------------
 *
 * HTML：
 * <textarea
 *     class="summernote"
 *     data-height="300"
 *     data-lang="en-US"
 * ></textarea>
 *
 * 說明：
 * - data-height → 覆寫 height
 * - data-lang   → 覆寫 lang
 *
 * 需搭配 getConfigFromData() 實作
 */

/* ------------------------------------------------------------
 * 範例 7：使用 data-preset 設定常用配置
 * ------------------------------------------------------------
 *
 * HTML：
 * <textarea
 *     class="summernote"
 *     data-preset="simple"
 * ></textarea>
 *
 * 說明：
 * - data-preset 可對應預先定義的 toolbar / config 組合
 * - 適合用於「簡易 / 進階 / 管理後台」等模式
 */
