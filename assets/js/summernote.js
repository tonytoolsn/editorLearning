import 'bootstrap/dist/js/bootstrap.bundle.min';
import 'bootstrap/dist/css/bootstrap.min.css';
import 'summernote/dist/summernote-bs5.min';
import 'summernote/dist/summernote-bs5.min.css';
import 'summernote/dist/lang/summernote-zh-TW.js';
import '../styles/summernote.css';
import { APP_URL } from './config.js';

export default class SummernoteManager {

    static DEFAULT_ACTIVE_CLASS = 'is-summernote-active';

    static SUPPORTED_CALLBACKS = [
        'onChange',
        'onImageUpload',
        'onMediaDelete',
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

    constructor({
        root = document,
        config  = {},
        activeClass = SummernoteManager.DEFAULT_ACTIVE_CLASS,
        autoInit = true
    } = {}) {
        this.$root = $(root);
        this.userConfig  = config;
        this.activeClass = activeClass;

        this.baseConfig = $.extend(true, {}, this.defaultConfig, this.userConfig);

        if (autoInit) {
            this.init();
        }
    }

    get targets() {
        const isDocument = this.$root[0] === document;
        const targetClass = isDocument ? 'summernote' : 'custom-summernote';

        return this.$root.hasClass(targetClass)
            ? this.$root
            : this.$root.find(`.${targetClass}`);
    }

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
                $textarea.summernote('insertImage', res.url);
            }
        });
    }

    deleteImage(textarea, src) {
        const $textarea = $(textarea);
        let deleteUrl = this.getAttr($textarea,'imageDeleteUrl');
        const uuidOrfilename = src.substring(src.lastIndexOf('/') + 1);

        $.ajax({
            url: APP_URL + deleteUrl + '/' + uuidOrfilename,
            type: "DELETE",
            data: { src: src },
            success: function(res) {
                console.log("刪除成功", res);
            }
        });
    }

    onImageUpload(textarea, files) {
        for(let i=0; i<files.length; i++) {
          this.uploadImage(textarea, files[i]);
        }
    }

    onMediaDelete(textarea, target) {
        deleteImage(textarea, target[0].src);
    }

    composeCallback({ internal, user, override }) {
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
            const textarea = this;
            if (typeof internal === 'function') internal.call(manager, textarea, ...args);
            if (typeof user === 'function') user.call(manager, textarea, ...args);
        };
    }

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

    buildButtons() {
        const internalButtons = {
            customDashHr: this.customDashHrButton
        };
        const userButtons = this.userConfig.buttons || {};
        const overrideButtons = this.userConfig.overrideButtons || {};

        return Object.keys({ ...internalButtons, ...userButtons, ...overrideButtons })
            .reduce((acc, key) => {
                acc[key] = overrideButtons[key] || userButtons[key] || internalButtons[key];
                return acc;
            }, {});
    }

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

    init() {
        this.targets.each((_, el) => {
            const $el = $(el);

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

    static initInstance(options = {}) {
        return new SummernoteManager({
            ...options,
            autoInit: true
        });
    }
}

window.wfs = window.wfs || {};
wfs.SummernoteManager = SummernoteManager;

$(function () {
    wfs.SummernoteManager.initInstance();
});
