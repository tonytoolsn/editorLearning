import 'summernote/dist/summernote-lite.min.js';
import 'summernote/dist/summernote-lite.min.css';
import 'summernote/dist/lang/summernote-zh-TW.js';
import '../styles/summernote.css';

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

    /** 支援的 callbacks（明確列出，避免魔法） */
    static SUPPORTED_CALLBACKS = [
        'onInit',
        'onChange',
        'onPaste',
        'onKeydown',
        'onFocus',
        'onBlur'
    ];

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
    onChange(contents, $editable) {
        const $editor = $(this).next('.note-editor');
        const classMap = {
            BLOCKQUOTE: 'note-custom-blockquote',
            PRE: 'note-custom-pre'
        };

        $editor
            .find('blockquote:not(.note-custom-blockquote), pre:not(.note-custom-pre)')
            .each(function () {
                const className = classMap[this.tagName];
                if (className) this.classList.add(className);
            });
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
        if (typeof override === 'function') {
            return override;
        }

        if (typeof internal === 'function' && typeof user === 'function') {
            return function (...args) {
                internal.apply(this, args);
                user.apply(this, args);
            };
        }

        return (internal || user)?.bind(this);
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
            customHr: this.customHrButton
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
            height: 300,
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
                ['insert', ['link','picture','video','table','hr','customHr']],
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

            callbacks: this.buildCallbacks(),

            buttons: this.buildButtons()
        };
    }

    /**
     * 合併後的最終設定
     *
     * 合併順序：
     * defaultConfig ← userConfig
     */
    get config() {
        return $.extend(true, {}, this.defaultConfig, this.userConfig);
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

            $el.summernote(this.config);
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
    wfs.SummernoteManager.initInstance()
});

/*
    範例：
    // 自動初始化頁面上所有 .summernote
    <textarea id="textarea1" class="summernote"></textarea>
    <textarea id="textarea2" class="summernote"></textarea>

    wfs.SummernoteManager.initInstance();
*/

/*
    範例：
    //----------手動控制場景----------
    // 初始化 #modal_name 內的 .custom-summernote

    <div id="modal_name" class="modal" tabindex="-1" role="dialog">
        ...
        <textarea class="custom-summernote"></textarea>
        ...
    </div>

    const summernoteManager = wfs.SummernoteManager.initInstance({
        root: '#modal_name',
        config: {
            height: 400
        },
        activeClass: 'my-summernote-active',
        autoInit: false
    });

    summernoteManager.init();
*/

/*
    範例：
    //----------手動控制場景----------
    // 初始化 #id_name 的 .custom-summernote

    <textarea id="id_name" class="custom-summernote"></textarea>

    wfs.SummernoteManager.initInstance({
        root: '#id_name',
        config: {
            height: 300
        },
    });
*/
