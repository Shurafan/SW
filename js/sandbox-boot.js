import { parse, compileScript, compileTemplate } from '@vue/compiler-sfc';
import { createApp } from 'vue';
import { Library } from '@studio-west/component-sw';

var DADATA_API_KEY = '6582965ff5937562597b8849ef712479971b1e28';

function collectReactiveVars(code) {
    var names = Object.create(null);
    code.replace(/\bv-model(?::[\w-]+)?="([^"]+)"/g, function (_, expr) {
        var name = expr.trim();
        if (/^[\w$]+$/.test(name)) names[name] = true;
    });
    code.replace(/:[A-Za-z_$][\w$-]*="([A-Za-z_$][\w$]*)"/g, function (_, name) {
        names[name] = true;
    });
    code.replace(/@[\w-]+="[^"]*?\b([A-Za-z_$][\w$]*)\s*=/g, function (_, name) {
        names[name] = true;
    });
    return Object.keys(names);
}

function ensureReactiveScript(code) {
    if (/<script[\s>]/i.test(code)) return code;
    var vars = collectReactiveVars(code);
    if (!vars.length) return code;
    var decls = vars.map(function (name) {
        return "const " + name + " = ref('')";
    }).join('\n');
    return '<script setup>\nimport { ref } from \'vue\'\n' + decls + '\n</script>\n\n' + code;
}

function isSimpleRefValue(value) {
    value = value.trim();
    if (/^(true|false|null|undefined|-?\d+(?:\.\d+)?)$/.test(value)) return true;
    if (/^'[^'\\]*'$/.test(value) || /^"[^"\\]*"$/.test(value)) return true;
    if (/^\{[^{}]*\}$/.test(value)) return true;
    if (/^\[[^\[\]]*\]$/.test(value)) return true;
    return false;
}

function repairReactiveModels(code) {
    var modelVars = collectReactiveVars(code);
    if (!modelVars.length) return code;

    function repairScriptBody(body) {
        var changed = false;
        modelVars.forEach(function (name) {
            var letRe = new RegExp('\\blet\\s+' + name + '\\s*=\\s*([^;\\n]+)');
            body = body.replace(letRe, function (match, value) {
                if (!isSimpleRefValue(value)) return match;
                changed = true;
                return 'const ' + name + ' = ref(' + value.trim() + ')';
            });
        });
        if (changed && !/\bimport\s*\{[^}]*\bref\b/.test(body)) {
            body = "import { ref } from 'vue'\n" + body;
        }
        return body;
    }

    return code.replace(
        /(<script(?:\s+setup)?[^>]*>)([\s\S]*?)(<\/script>)/gi,
        function (_, open, body, close) {
            return open + repairScriptBody(body) + close;
        }
    );
}

function injectDadataToken(code) {
    if (!/<sw-select\b/i.test(code)) return code;

    code = code.replace(/:token="\$\{API_KEY\}"/g, ':token="token"');
    code = code.replace(
        /const\s+token\s*=\s*ref\(\s*(['"])\s*\1\s*\)/g,
        "const token = ref('" + DADATA_API_KEY + "')"
    );

    if (!/\btoken\s*=/.test(code)) {
        code = code.replace(
            /(<script\s+setup[^>]*>\n(?:import[^\n]+\n)*)/i,
            "$1const token = ref('" + DADATA_API_KEY + "')\n"
        );
    }

    return code;
}

function unwrapOuterTemplate(code) {
    // README иногда кладёт разметку в <template>…</template>;
    // sandbox потом оборачивает ещё раз → Vue рендерит нативный <template>
    // (содержимое не видно в DOM). Снимаем только внешнюю обёртку.
    return code.replace(
        /^\s*<template\s*>\s*([\s\S]*?)\s*<\/template>\s*$/i,
        '$1'
    );
}

function repairSnippet(code) {
    code = ensureReactiveScript(code);
    code = repairReactiveModels(code);
    code = code
        .replace(
            /(:data="\{startDate:\s*'[^']*')\s+(endDate:)/g,
            '$1, $2'
        )
        .replace(
            /\blimitation="\s*(\[[\s\S]*?\])"/g,
            ':limitation="$1"'
        );
    code = injectDadataToken(code);
    return code;
}

function parseSnippet(code) {
    var scripts = [];
    var styles = [];
    var template = code;

    template = template.replace(
        /<script\s+setup([^>]*)>([\s\S]*?)<\/script>/gi,
        function (_, attrs, body) {
            scripts.push({ setup: true, attrs: attrs || '', body: body.trim() });
            return '';
        }
    );
    template = template.replace(
        /<script([^>]*)>([\s\S]*?)<\/script>/gi,
        function (_, attrs, body) {
            if (/setup/i.test(attrs)) return _;
            scripts.push({ setup: false, attrs: attrs || '', body: body.trim() });
            return '';
        }
    );
    template = template.replace(
        /<style([^>]*)>([\s\S]*?)<\/style>/gi,
        function (_, attrs, body) {
            styles.push({ attrs: attrs || '', body: body.trim() });
            return '';
        }
    );

    template = unwrapOuterTemplate(template.trim());

    return { scripts: scripts, styles: styles, template: template.trim() };
}

function normalizeParsed(parsed) {
    var setupScripts = parsed.scripts.filter(function (s) { return s.setup; });
    var plainScripts = parsed.scripts.filter(function (s) { return !s.setup; });

    if (plainScripts.length) {
        var merged = plainScripts.map(function (s) {
            return s.body.replace(/\bvar\b/g, 'let');
        }).join('\n');
        setupScripts.push({ setup: true, attrs: '', body: merged });
    }

    return {
        scripts: setupScripts,
        styles: parsed.styles,
        template: parsed.template
    };
}

function toSfcSource(parsed) {
    var parts = [];

    parsed.scripts.forEach(function (s) {
        parts.push('<script setup' + s.attrs + '>\n' + s.body + '\n</script>');
    });
    parsed.styles.forEach(function (s) {
        parts.push('<style' + s.attrs + '>\n' + s.body + '\n</style>');
    });
    if (parsed.template) {
        parts.push('<template>\n' + parsed.template + '\n</template>');
    }

    return parts.join('\n\n');
}

function injectStyles(styles) {
    styles.forEach(function (s) {
        var el = document.createElement('style');
        if (/scoped/i.test(s.attrs)) el.setAttribute('scoped', '');
        el.textContent = s.body;
        document.head.appendChild(el);
    });
}

function showError(mount, error) {
    mount.innerHTML = '<pre style="margin:0;padding:12px;color:#c00;white-space:pre-wrap;font:12px/1.4 monospace">'
        + String(error && error.message ? error.message : error)
        + '</pre>';
}

function resizeLater() {
    function send() {
        try {
            parent.postMessage({
                type: 'sw-sandbox-resize',
                height: document.documentElement.scrollHeight
            }, '*');
        } catch (e) { /* noop */ }
    }
    send();
    setTimeout(send, 100);
    setTimeout(send, 500);
    setTimeout(send, 1500);
}

async function compileSfc(sfcSource) {
    var filename = 'App.vue';
    var id = 'sw-' + Math.random().toString(36).slice(2, 8);
    var parsed = parse(sfcSource, { filename: filename });

    if (parsed.errors.length) {
        throw new Error(parsed.errors.map(function (e) { return e.message; }).join('\n'));
    }

    if (!parsed.descriptor.template && !parsed.descriptor.scriptSetup && !parsed.descriptor.script) {
        throw new Error('Пустой фрагмент');
    }

    var script = compileScript(parsed.descriptor, { id: id });
    var code = script.content;

    if (parsed.descriptor.template) {
        var template = compileTemplate({
            id: id,
            source: parsed.descriptor.template.content,
            filename: filename,
            scoped: parsed.descriptor.styles.some(function (s) { return s.scoped; }),
            compilerOptions: {
                bindingMetadata: script.bindings,
                isCustomElement: function (tag) { return tag === 'svg-icon'; }
            }
        });

        if (template.errors.length) {
            throw new Error(template.errors.map(function (e) { return e.toString(); }).join('\n'));
        }

        code = code.replace(/export default/, 'const __sfc__ =');
        code += '\n' + template.code.replace(/export function render/, 'function render');
        code += '\n__sfc__.render = render;\nexport default __sfc__;';
    }

    var blob = new Blob([code], { type: 'text/javascript' });
    var url = URL.createObjectURL(blob);

    try {
        return await import(url);
    } finally {
        URL.revokeObjectURL(url);
    }
}

async function mountSnippet(source, mount) {
    source = repairSnippet(source);
    var parsed = normalizeParsed(parseSnippet(source));
    injectStyles(parsed.styles);

    if (!parsed.scripts.length && parsed.template) {
        createApp({ template: parsed.template }).use(Library).mount(mount);
        resizeLater();
        return;
    }

    var mod = await compileSfc(toSfcSource(parsed));
    createApp(mod.default).use(Library).mount(mount);
    resizeLater();
}

async function boot() {
    var mount = document.createElement('div');
    document.body.appendChild(mount);
    var source = document.getElementById('sw-tpl').textContent.trim();

    try {
        await mountSnippet(source, mount);
    } catch (error) {
        showError(mount, error);
        resizeLater();
    }
}

boot();
