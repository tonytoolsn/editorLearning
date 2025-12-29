import 'summernote/dist/summernote-lite.min.js';
import 'summernote/dist/summernote-lite.min.css';
import 'summernote/dist/lang/summernote-zh-TW.js';
import '../styles/summernote.css';

function initSummernote({
    root = document,
    editorConfig = {},
} = {}) {



    const defaultConfig = {
        // --- 基礎配置 ---
        placeholder: '请输入内容',     // 編輯器佔位符文字
        tabsize: 2,                   // Tab 鍵縮排空白數
        height: 300,                  // 編輯器高度（px）
        minHeight: null,              // 最小高度
        maxHeight: null,              // 最大高度
        focus: true,                  // 頁面加載完成後自動聚焦
        airMode: false,               // 氣泡模式：只顯示文字，工具欄浮動
        disableDragAndDrop: true,     // 禁止拖曳上傳圖片或文字
        dialogsInBody: true,          // 彈窗插入 body 元素，避免 z-index 問題
        direction: 'ltr',             // 文本方向 ltr / rtl
        lang: 'zh-TW',                // 語言包

        // --- 工具列配置 ---
        // https://summernote.org/deep-dive/#custom-toolbar
        toolbar: [
            // Insert
            ['insert', [    // 插入
                'picture',  // 開啟圖像對話框
                'link',     // 開啟連結對話框
                'video',    // 開啟視訊對話框
                'table',    // 插入表格
                'hr'        // 插入一條水平線
            ]],
            // Font Style
            ['font', [           // 字體樣式
                'fontname',      // 字體名稱
                'fontsize',      // 字體大小
                'fontsizeunit',  // 字體大小單位
                'color',         // 字體顏色
                'forecolor',     // 前景色
                'backcolor',     // 背景色
                'bold',          // 粗體
                'italic',        // 斜體
                'underline',     // 下劃線
                'strikethrough', // 刪除線
                'superscript',   // 上標
                'subscript',     // 下標
                'clear'          // 清除格式
            ]],
            // Paragraph Style
            ['para', [       // 段落樣式
                'style',     // 段落樣式
                'ol',        // 有序列表
                'ul',        // 無序列表
                'paragraph', // 段落格式
                'height'     // 行高
            ]],
            // Misc
            ['view', [        // 其他
                'fullscreen', // 全螢幕
                'codeview',   // 原始碼檢視
                'undo',       // 復原
                'redo',       // 重做
                'help'        // 幫助
            ]]
        ],
         // --- 樣式 / 字體 ---
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
        // 忽略字體檢查
        fontNamesIgnoreCheck: [
            '微軟正黑體',
            'Microsoft JhengHei',
            'PingFang TC'
        ],
        callbacks: {
            onChange: function (contents) {
                const $editor = $(this).next('.note-editor');
                const classMap = {
                    BLOCKQUOTE: 'note-custom-blockquote',
                    PRE: 'note-custom-pre'
                };

                $editor.find('blockquote:not(.note-custom-blockquote), pre:not(.note-custom-pre)')
                    .each(function() {
                        const className = classMap[this.tagName];
                        if (className) this.classList.add(className);
                    });
            },
            // --- 圖片配置 ---
            onImageUpload: function(files) {
                // 自訂圖片上傳
            },
            onMediaDelete: function(target) {
                // 圖片刪除回調
            }
        },
        fontSizes: ['8','9','10','11','12','14','16','18','20','22','24','28','30','36','48','72'], // 字體大小選項
        lineHeights: ['1.0','1.2','1.4','1.5','1.6','2.0','3.0'],   // 行高選項
        buttons: {
            customHr: function(context) {
                const ui = $.summernote.ui;
                return ui.button({
                    contents: '<span class="note-icon-dash-btn"></span>',
                        tooltip: '虛線',
                        click: function() {
                            const node = $('<hr class="note-custom-dash-hr">')[0];
                            context.invoke('editor.insertNode', node);
                        }
                    }).render();
            },
        },

        // --- 鏈接 / 視頻 ---
        dialogsInBody: true,           // modal 插入 body 元素，避免 z-index 問題
        popover: {
            image: [
                ['image', ['resizeFull', 'resizeHalf', 'resizeQuarter', 'resizeNone']],
                ['float', ['floatLeft', 'floatRight', 'floatNone']],
                ['remove', ['removeMedia']]
            ],
            link: [
                ['link', ['linkDialogShow', 'unlink']]
            ],
            air: [
                ['color', ['color']],
                ['font', ['bold', 'underline', 'clear']]
            ]
        },

        // --- 高級 ---
        codemirror: { // 如果啟用 codeview 使用 CodeMirror
            theme: 'monokai',
            lineNumbers: true,
            mode: 'htmlmixed'
        },
        styleTags: ['p', 'blockquote', 'pre', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6'],  // 樣式標籤選項
        shortcuts: true,               // 是否啟用快速鍵
        airPopover: [
            ['color', ['color']],
            ['font', ['bold', 'underline', 'clear']]
        ],
        hint: null,                    // 自動補全功能
        // hint: {
        //     match: /#(\w*)$/,
        //     search: function (keyword, callback) {
        //         callback(['Symfony', 'Summernote', 'Webpack']);
        //     },
        //     content: function (item) {
        //         return '#' + item + ' ';
        //     }
        // }
    };

    const summernoteConfig = $.extend(true, {}, defaultConfig, editorConfig);

    const $root = $(root);
    const targetClass = root === document ? 'summernote' : 'custom-summernote';
    const $targets = $root.hasClass(targetClass) ? $root : $root.find(`.${targetClass}`);

    $targets.each(function () {
        const $el = $(this);
        if ($el.hasClass('active')) return;
        $el.summernote(summernoteConfig);
        $el.addClass('active');
    });
}

$(function () {
    initSummernote();
});
