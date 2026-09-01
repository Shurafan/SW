/**
 * SwCodeSandbox — Vue 3: превращает <pre><code class="language-html"> в live-песочницу.
 * При наличии <sw-*> в коде автоматически подключает @studio-west/component-sw в iframe.
 */
(function () {
    'use strict';

    if (typeof Vue === 'undefined') return;

    var PROCESSED = 'data-sw-sandbox';
    var SANDBOX_LANGS = ['html', 'xml'];
    var TABS = [
        { id: 'preview', label: 'Превью' },
        { id: 'code', label: 'Код' }
    ];
    var { createApp, h } = Vue;

    function getCdnConfig() {
        var cfg = window.SwCodeSandboxConfig || {};
        var unpkg = (cfg.unpkgBase || 'https://unpkg.com').replace(/\/$/, '');
        var origin = (typeof window !== 'undefined' && window.location && window.location.origin)
            ? window.location.origin
            : '';
        return {
            version: cfg.componentSwVersion || 'latest',
            vueVersion: cfg.vueVersion || '3',
            compilerVersion: cfg.compilerVersion || '3',
            unpkg: unpkg,
            shims: cfg.esModuleShimsUrl || (unpkg + '/es-module-shims@1.10.0/dist/es-module-shims.js'),
            bootUrl: cfg.sandboxBootUrl || (origin + '/assets/teme_sw/js/sandbox-boot.js')
        };
    }

    function needsComponents(code) {
        return /<sw-[\w-]+/i.test(code);
    }

    function lightHeadStyle() {
        return '<style>'
            + 'html.light,:root{color-scheme:light}'
            + 'body{margin:8px;font-family:sans-serif;color:#333;background:#fff}'
            + '</style>';
    }

    function escapeTextarea(text) {
        return text.replace(/<\/textarea/gi, '<\\/textarea');
    }

    function escapeScriptBreaks(html) {
        return html.replace(/<\/script/gi, '<\\/script');
    }

    function componentHead(pkgBase, vueUrl, shimsUrl, compilerUrl) {
        var importMap = JSON.stringify({
            imports: {
                vue: vueUrl,
                '@vue/compiler-sfc': compilerUrl,
                '@studio-west/component-sw': pkgBase + '/dist/index.js'
            }
        });

        return '<meta charset="utf-8">'
            + '<script src="' + shimsUrl + '"><\/script>'
            + '<script type="importmap">' + importMap + '<\/script>'
            + '<meta name="color-scheme" content="light">'
            + lightHeadStyle()
            + '<link rel="stylesheet" href="' + pkgBase + '/dist/component-sw.css">'
            + '<script>document.documentElement.classList.add("light");<\/script>';
    }

    function componentBody(code, bootModule) {
        var safeBoot = bootModule.replace(/<\/script/gi, '<\\/script');
        return '<textarea id="sw-tpl" hidden>'
            + escapeTextarea(code)
            + '</textarea>'
            + '<script type="module">\n'
            + safeBoot
            + '\n<\/script>';
    }

    function buildComponentSrcdoc(code, pkgBase, vueUrl, shimsUrl, compilerUrl, bootModule) {
        var head = componentHead(pkgBase, vueUrl, shimsUrl, compilerUrl);
        var body = componentBody(code, bootModule);
        return '<!DOCTYPE html><html class="light"><head>' + head + '</head><body>' + body + '</body></html>';
    }

    function buildErrorSrcdoc(message) {
        return '<!DOCTYPE html><html class="light"><head><meta charset="utf-8">'
            + lightHeadStyle()
            + '</head><body><pre style="margin:0;padding:12px;color:#c00">'
            + escapeScriptBreaks(message)
            + '</pre></body></html>';
    }

    function ensureLightHtml(doc) {
        if (/<html[\s>]/i.test(doc)) {
            return doc.replace(/<html([^>]*)>/i, function (match, attrs) {
                if (/class\s*=/i.test(attrs)) {
                    return match.replace(
                        /class\s*=\s*(["'])([^"']*)(["'])/i,
                        function (m, q, classes, q2) {
                            return /(?:^|\s)light(?:\s|$)/.test(classes)
                                ? m
                                : 'class=' + q + classes + ' light' + q2;
                        }
                    );
                }
                return '<html class="light"' + attrs + '>';
            });
        }
        return doc;
    }

    function applyLightDoc(doc) {
        doc = ensureLightHtml(doc);
        if (!/<meta[^>]+name=["']color-scheme["']/i.test(doc) && /<head[\s>]/i.test(doc)) {
            doc = doc.replace(
                /<head([^>]*)>/i,
                '<head$1><meta name="color-scheme" content="light">' + lightHeadStyle()
            );
        }
        return doc;
    }

    function buildSrcdoc(code, bootModule) {
        code = (code || '').trim();
        if (!code) {
            return '<!DOCTYPE html><html class="light"><head><meta charset="utf-8">'
                + '<meta name="color-scheme" content="light">'
                + lightHeadStyle()
                + '</head><body></body></html>';
        }

        var cdn = getCdnConfig();
        var pkgBase = cdn.unpkg + '/@studio-west/component-sw@' + cdn.version;
        var vueUrl = cdn.unpkg + '/vue@' + cdn.vueVersion + '/dist/vue.esm-browser.prod.js';
        var compilerUrl = cdn.unpkg + '/@vue/compiler-sfc@' + cdn.compilerVersion + '/dist/compiler-sfc.esm-browser.js';
        var fullDoc = /^<!DOCTYPE/i.test(code) || /^<html[\s>]/i.test(code);
        var withComponents = needsComponents(code);

        if (withComponents) {
            return buildComponentSrcdoc(code, pkgBase, vueUrl, cdn.shims, compilerUrl, bootModule || '');
        }

        if (fullDoc) return applyLightDoc(escapeScriptBreaks(code));

        return '<!DOCTYPE html><html class="light"><head><meta charset="utf-8">'
            + '<meta name="color-scheme" content="light">'
            + lightHeadStyle()
            + '</head><body>' + escapeScriptBreaks(code) + '</body></html>';
    }

    var bootModuleCache = null;
    var bootModulePromise = null;

    function getBootModule() {
        if (bootModuleCache) return Promise.resolve(bootModuleCache);
        if (!bootModulePromise) {
            bootModulePromise = fetch(getCdnConfig().bootUrl)
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error('HTTP ' + response.status);
                    }
                    return response.text();
                })
                .then(function (text) {
                    bootModuleCache = text;
                    return text;
                });
        }
        return bootModulePromise;
    }

    function preloadBootModule() {
        getBootModule().catch(function () { /* warm cache */ });
    }

    function isSandboxContext(el) {
        return !!(el && el.closest && el.closest(
            '.sw-code-sandbox, .sw-code-sandbox-mount, [' + PROCESSED + ']'
        ));
    }

    function writeIframe(iframe, code) {
        if (!iframe || iframe.dataset.swLoaded) return;

        code = (code || '').trim();
        if (needsComponents(code)) {
            getBootModule()
                .then(function (boot) {
                    iframe.srcdoc = buildSrcdoc(code, boot);
                    iframe.dataset.swLoaded = '1';
                })
                .catch(function (error) {
                    iframe.srcdoc = buildErrorSrcdoc(
                        'Не удалось загрузить sandbox-boot.js: ' + (error.message || error)
                    );
                    iframe.dataset.swLoaded = '1';
                });
            return;
        }

        iframe.srcdoc = buildSrcdoc(code);
        iframe.dataset.swLoaded = '1';
    }

    function panelProps(active, id) {
        return {
            class: 'sw-code-sandbox__panel',
            role: 'tabpanel',
            id: id,
            hidden: active ? null : ''
        };
    }

    var CodeSandbox = {
        props: {
            code: { type: String, required: true },
            lang: { type: String, default: 'html' }
        },
        data: function () {
            return { tab: 'preview', uid: 'sw-sb-' + Math.random().toString(36).slice(2, 9) };
        },
        mounted: function () {
            var self = this;
            this._onResizeMsg = function (e) {
                if (!self._iframe || e.source !== self._iframe.contentWindow) return;
                if (!e.data || e.data.type !== 'sw-sandbox-resize') return;
                self._iframe.style.height = Math.max((e.data.height || 0) + 16, 80) + 'px';
            };
            window.addEventListener('message', this._onResizeMsg);
        },
        unmounted: function () {
            if (this._onResizeMsg) {
                window.removeEventListener('message', this._onResizeMsg);
            }
        },
        methods: {
            activateTab: function (id) {
                if (id === 'preview' || id === 'code') this.tab = id;
            },
            onTabsClick: function (e) {
                var btn = e.target.closest('[data-tab]');
                if (!btn || !this.$el.contains(btn)) return;
                e.preventDefault();
                this.activateTab(btn.getAttribute('data-tab'));
            },
            onTabsKeydown: function (e) {
                if (e.key !== 'ArrowLeft' && e.key !== 'ArrowRight') return;
                e.preventDefault();
                this.activateTab(this.tab === 'preview' ? 'code' : 'preview');
            },
            bindIframe: function (el) {
                this._iframe = el;
                writeIframe(el, this.code);
            }
        },
        render: function () {
            var self = this;
            var tab = this.tab;
            var code = this.code;
            var uid = this.uid;

            return h('div', {
                class: 'sw-code-sandbox light',
                'data-sw-sandbox': 'widget'
            }, [
                h('div', {
                    class: 'sw-code-sandbox__tabs',
                    role: 'tablist',
                    'aria-label': 'Песочница',
                    onClick: this.onTabsClick,
                    onKeydown: this.onTabsKeydown
                }, TABS.map(function (item) {
                    var active = tab === item.id;
                    return h('button', {
                        key: item.id,
                        type: 'button',
                        role: 'tab',
                        class: ['sw-code-sandbox__tab', active ? 'is-active' : null],
                        'data-tab': item.id,
                        id: uid + '-tab-' + item.id,
                        'aria-selected': active ? 'true' : 'false',
                        'aria-controls': uid + '-panel-' + item.id,
                        tabindex: active ? 0 : -1
                    }, item.label);
                })),
                h('div', Object.assign({}, panelProps(tab === 'preview', uid + '-panel-preview'), {
                    'aria-labelledby': uid + '-tab-preview'
                }), [
                    h('iframe', {
                        class: 'sw-code-sandbox__iframe',
                        sandbox: 'allow-scripts',
                        title: 'HTML preview',
                        onVnodeMounted: function (vnode) {
                            self.bindIframe(vnode.el);
                        }
                    })
                ]),
                h('pre', Object.assign({}, panelProps(tab === 'code', uid + '-panel-code'), {
                    class: 'sw-code-sandbox__panel sw-code-sandbox__code',
                    'aria-labelledby': uid + '-tab-code',
                    'data-sw-sandbox': 'source'
                }), [
                    h('code', {}, code)
                ])
            ]);
        }
    };

    function getLang(codeEl) {
        var m = codeEl.className.match(/language-(\S+)/);
        return m ? m[1].toLowerCase() : 'text';
    }

    function shouldSandbox(lang) {
        return SANDBOX_LANGS.indexOf(lang) !== -1;
    }

    function processBlock(pre) {
        if (pre.hasAttribute(PROCESSED) || isSandboxContext(pre)) return;

        var codeEl = pre.querySelector('code[class*="language-"]');
        if (!codeEl) return;

        var lang = getLang(codeEl);
        if (!shouldSandbox(lang)) return;

        pre.setAttribute(PROCESSED, '1');
        var code = codeEl.textContent;
        var mount = document.createElement('div');
        mount.className = 'sw-code-sandbox-mount';
        mount.setAttribute(PROCESSED, '1');
        pre.parentNode.insertBefore(mount, pre);
        pre.parentNode.removeChild(pre);

        createApp(CodeSandbox, { code: code, lang: lang }).mount(mount);
    }

    function scan(root) {
        var scope = root && root.querySelectorAll ? root : document;
        var nodes = scope === document
            ? document.querySelectorAll('pre:not([' + PROCESSED + '])')
            : (scope.matches && scope.matches('pre:not([' + PROCESSED + '])')
                ? [scope]
                : scope.querySelectorAll('pre:not([' + PROCESSED + '])'));

        Array.prototype.forEach.call(nodes, function (pre) {
            if (isSandboxContext(pre)) return;
            if (pre.querySelector('code[class*="language-"]')) {
                processBlock(pre);
            }
        });
    }

    function observe() {
        var target = document.querySelector('#content') || document.body;
        if (!target || typeof MutationObserver === 'undefined') return;

        new MutationObserver(function (mutations) {
            mutations.forEach(function (m) {
                Array.prototype.forEach.call(m.addedNodes, function (node) {
                    if (node.nodeType !== 1 || isSandboxContext(node)) return;
                    scan(node);
                });
            });
        }).observe(target, { childList: true, subtree: true });
    }

    window.SwCodeSandbox = { scan: scan, getCdnConfig: getCdnConfig, preloadBootModule: preloadBootModule };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            preloadBootModule();
            scan();
            observe();
        });
    } else {
        preloadBootModule();
        scan();
        observe();
    }
})();
