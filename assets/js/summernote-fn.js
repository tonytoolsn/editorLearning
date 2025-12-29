import 'summernote/dist/summernote-lite.min.js';
import 'summernote/dist/summernote-lite.min.css';
import 'summernote/dist/lang/summernote-zh-TW.js';
import '../styles/summernote.css';

function initSummernote({
    root = document,
    editorConfig = {},
} = {}) {

    const $root = $(root);
    const targetClass = root === document ? 'summernote' : 'custom-summernote';
    const $targets = $root.hasClass(targetClass) ? $root : $root.find(`.${targetClass}`);

    const defaultConfig = {
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
            }
        },
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
    };

    const summernoteConfig = $.extend(true, {}, defaultConfig, editorConfig);

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
