/**
 * Оплата: модальное окно + страница holidays-calendar (возврат с Т‑Банка).
 */
(function () {
    'use strict';

    var defaultEndpoint = '/ajaxress';
    var payFormBox = document.getElementById('pay-modal-form');
    var payOverlay = document.querySelector('.owerlei');

    function isPayModalOpen() {
        return !!(payFormBox && (payFormBox.style.display === 'block' || payFormBox.classList.contains('is-open')));
    }

    function getEndpoint(root) {
        var el = root || document.querySelector('.pay[data-pay-endpoint]');
        return (el && el.getAttribute('data-pay-endpoint')) || defaultEndpoint;
    }

    function postAction(endpoint, action, data) {
        var body = new FormData();
        body.append('action', action);
        Object.keys(data).forEach(function (key) {
            body.append(key, data[key]);
        });
        return fetch(endpoint, {
            method: 'POST',
            body: body,
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        }).then(function (res) {
            return res.json().catch(function () {
                return { ok: false, error: 'Некорректный ответ сервера' };
            });
        });
    }

    function showResultGlobal(text) {
        var resultBox = document.getElementById('pay-result');
        var resultOut = document.querySelector('#pay-result [data-pay-result], [data-pay-result]');
        if (!resultBox || !resultOut) return;
        resultBox.hidden = !text;
        resultOut.textContent = text || '';
        if (text) resultBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function openPayModal() {
        if (!payFormBox || !payOverlay) {
            window.location.href = '/holidays-calendar';
            return;
        }
        var projectForm = document.querySelector('.modal-form:not(.modal-form--pay)');
        if (projectForm) projectForm.style.display = 'none';
        payOverlay.style.display = 'block';
        payOverlay.style.background = '#0009';
        payFormBox.style.display = 'block';
        payFormBox.classList.add('is-open');
        var w = document.body.offsetWidth;
        document.body.style.overflow = 'hidden';
        document.body.style.width = w + 'px';
    }

    function closePayModal() {
        if (!payFormBox || !payOverlay) return;
        payFormBox.style.display = 'none';
        payFormBox.classList.remove('is-open');
        var projectForm = document.querySelector('.modal-form:not(.modal-form--pay)');
        var projOpen = projectForm && projectForm.style.display === 'block';
        if (!projOpen) {
            payOverlay.style.background = '#0000';
            payOverlay.style.display = 'none';
            document.body.style.overflow = 'initial';
            document.body.style.width = 'auto';
        }
    }

    function initPayRoot(root) {
        if (!root || root.dataset.payReady === '1') return;
        root.dataset.payReady = '1';

        var endpoint = getEndpoint(root);
        var priceYear = parseInt(root.getAttribute('data-price-year') || '500', 10) || 500;
        var priceYearCompany = parseInt(root.getAttribute('data-price-year-company') || '5000', 10) || 5000;
        var resultBox = root.querySelector('.pay-result');
        var resultOut = root.querySelector('[data-pay-result-modal], [data-pay-result]');
        var tabs = root.querySelectorAll('[data-pay-tab]');
        var forms = root.querySelectorAll('[data-pay-form]');

        function formatSum(n) {
            return String(n).replace(/\B(?=(\d{3})+(?!\d))/g, '\u00a0') + '\u00a0₽';
        }

        function yearsOf(form) {
            var checked = form.querySelector('input[name="years"]:checked');
            return checked ? parseInt(checked.value, 10) : 1;
        }

        function updateSums() {
            forms.forEach(function (form) {
                var el = form.querySelector('[data-pay-sum]');
                if (!el) return;
                var rate = form.getAttribute('data-pay-form') === 'company' ? priceYearCompany : priceYear;
                el.textContent = formatSum(rate * yearsOf(form));
            });
        }

        function showMsg(form, text, isError) {
            var msg = form.querySelector('[data-pay-msg]');
            if (!msg) return;
            msg.hidden = !text;
            msg.textContent = text || '';
            msg.classList.toggle('is-error', !!isError);
        }

        function showResult(text) {
            if (resultBox && resultOut) {
                resultBox.hidden = !text;
                resultOut.textContent = text || '';
            } else {
                showResultGlobal(text);
            }
        }

        function setLoading(form, on) {
            var btn = form.querySelector('[type="submit"]');
            if (!btn) return;
            btn.disabled = !!on;
            btn.classList.toggle('is-loading', !!on);
        }

        function fieldVal(form, name) {
            var el = form.elements.namedItem(name);
            return el && 'value' in el ? String(el.value).trim() : '';
        }

        function fieldWrap(form, name) {
            var el = form.elements.namedItem(name);
            return el && el.closest ? el.closest('.wrapper-textfield') : null;
        }

        function validateTarget(value) {
            var t = String(value || '').trim();
            if (!t || t.length > 253) return false;
            if (/^(?:\d{1,3}\.){3}\d{1,3}$/.test(t)) {
                return t.split('.').every(function (oct) {
                    var n = +oct;
                    return n >= 0 && n <= 255;
                });
            }
            if (/^[0-9a-f:.]+$/i.test(t) && t.indexOf(':') !== -1) return true;
            return /^(?:[a-z\u0430-\u044f\u04510-9](?:[a-z\u0430-\u044f\u04510-9-]{0,61}[a-z\u0430-\u044f\u04510-9])?\.)+[a-z\u0430-\u044f\u0451]{2,}$/iu.test(t);
        }

        function validateEmail(value) {
            var e = String(value || '').trim();
            return !!e && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(e);
        }

        function validateInn(value) {
            var inn = String(value || '').replace(/\D+/g, '');
            return /^\d{10}$|^\d{12}$/.test(inn);
        }

        function clearFieldErrors(form) {
            form.querySelectorAll('.pay-field-error').forEach(function (el) {
                el.classList.remove('pay-field-error');
            });
            form.querySelectorAll('[data-pay-field-msg]').forEach(function (hint) {
                hint.hidden = true;
                hint.textContent = '';
            });
        }

        function clearFieldError(form, name) {
            if (name === 'years') {
                var fs = form.querySelector('.pay-years');
                if (fs) fs.classList.remove('pay-field-error');
            } else {
                var wrap = fieldWrap(form, name);
                if (wrap) {
                    wrap.classList.remove('pay-field-error');
                    var hint = wrap.querySelector('[data-pay-field-msg]');
                    if (hint) {
                        hint.hidden = true;
                        hint.textContent = '';
                    }
                }
            }
            if (!form.querySelector('.pay-field-error')) {
                showMsg(form, '', false);
            }
        }

        function setFieldError(form, name, message) {
            if (name === 'years') {
                var fieldset = form.querySelector('.pay-years');
                if (fieldset) fieldset.classList.add('pay-field-error');
                return;
            }
            var wrap = fieldWrap(form, name);
            if (!wrap) return;
            wrap.classList.add('pay-field-error');
            var hint = wrap.querySelector('[data-pay-field-msg]');
            if (!hint) {
                hint = document.createElement('p');
                hint.className = 'pay-field-msg is-error';
                hint.setAttribute('data-pay-field-msg', '');
                wrap.appendChild(hint);
            }
            hint.hidden = false;
            hint.textContent = message;
        }

        function showValidationErrors(form, errors) {
            clearFieldErrors(form);
            if (!errors.length) {
                showMsg(form, '', false);
                return false;
            }
            errors.forEach(function (err) {
                setFieldError(form, err.name, err.message);
            });
            var summary = errors.length === 1
                ? errors[0].message
                : 'Исправьте ошибки в форме:\n' + errors.map(function (e) { return '• ' + e.message; }).join('\n');
            showMsg(form, summary, true);
            var firstEl = form.elements.namedItem(errors[0].name);
            if (firstEl && typeof firstEl.focus === 'function') {
                firstEl.focus();
            }
            return false;
        }

        function validatePersonForm(form) {
            var errors = [];
            var target = fieldVal(form, 'target');
            var email = fieldVal(form, 'email');
            var years = yearsOf(form);

            if (!target) {
                errors.push({ name: 'target', message: 'Укажите IP или домен' });
            } else if (!validateTarget(target)) {
                errors.push({ name: 'target', message: 'Некорректный IP или домен. Пример: 192.168.0.1 или example.ru' });
            }
            if (!email) {
                errors.push({ name: 'email', message: 'Укажите e-mail для отправки результата' });
            } else if (!validateEmail(email)) {
                errors.push({ name: 'email', message: 'Укажите корректный e-mail' });
            }
            if (years !== 1 && years !== 2) {
                errors.push({ name: 'years', message: 'Выберите срок: 1 или 2 года' });
            }
            return { ok: !errors.length, errors: errors };
        }

        function validateCompanyForm(form) {
            var errors = [];
            var inn = fieldVal(form, 'inn');
            var company = fieldVal(form, 'company');
            var target = fieldVal(form, 'target');
            var email = fieldVal(form, 'email');
            var years = yearsOf(form);

            if (!inn) {
                errors.push({ name: 'inn', message: 'Укажите ИНН организации' });
            } else if (!validateInn(inn)) {
                errors.push({ name: 'inn', message: 'ИНН должен содержать 10 или 12 цифр' });
            }
            if (!company) {
                errors.push({ name: 'company', message: 'Укажите наименование организации' });
            }
            if (!target) {
                errors.push({ name: 'target', message: 'Укажите IP или домен' });
            } else if (!validateTarget(target)) {
                errors.push({ name: 'target', message: 'Некорректный IP или домен. Пример: 192.168.0.1 или example.ru' });
            }
            if (!email) {
                errors.push({ name: 'email', message: 'Укажите e-mail для счёта' });
            } else if (!validateEmail(email)) {
                errors.push({ name: 'email', message: 'Укажите корректный e-mail' });
            }
            if (years !== 1 && years !== 2) {
                errors.push({ name: 'years', message: 'Выберите срок: 1 или 2 года' });
            }
            return { ok: !errors.length, errors: errors };
        }

        function bindValidationClear(form) {
            form.addEventListener('input', function (e) {
                var name = e.target && e.target.name;
                if (name) clearFieldError(form, name);
            });
            form.addEventListener('change', function (e) {
                if (e.target && e.target.name === 'years') clearFieldError(form, 'years');
            });
        }

        function post(action, data) {
            return postAction(endpoint, action, data);
        }

        tabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                var name = tab.getAttribute('data-pay-tab');
                tabs.forEach(function (t) {
                    var active = t === tab;
                    t.classList.toggle('is-active', active);
                    t.setAttribute('aria-selected', active ? 'true' : 'false');
                });
                forms.forEach(function (form) {
                    var match = form.getAttribute('data-pay-form') === name;
                    form.classList.toggle('is-active', match);
                    form.hidden = !match;
                });
                var personForm = root.querySelector('#pay-person');
                var companyForm = root.querySelector('#pay-company');
                if (personForm) showMsg(personForm, '', false);
                if (companyForm) showMsg(companyForm, '', false);
                if (personForm) clearFieldErrors(personForm);
                if (companyForm) clearFieldErrors(companyForm);
            });
        });

        forms.forEach(function (form) {
            form.addEventListener('change', updateSums);
            form.addEventListener('input', updateSums);
        });
        updateSums();

        var personForm = root.querySelector('#pay-person');
        if (personForm) {
            bindValidationClear(personForm);
            personForm.addEventListener('submit', function (e) {
                e.preventDefault();
                var validation = validatePersonForm(personForm);
                if (!validation.ok) {
                    showValidationErrors(personForm, validation.errors);
                    return;
                }
                showMsg(personForm, '', false);
                clearFieldErrors(personForm);
                setLoading(personForm, true);
                post('pay_init', {
                    target: fieldVal(personForm, 'target'),
                    years: String(yearsOf(personForm)),
                    email: fieldVal(personForm, 'email')
                }).then(function (data) {
                    setLoading(personForm, false);
                    if (!data.ok) {
                        showMsg(personForm, data.error || 'Не удалось создать платёж', true);
                        return;
                    }
                    if (data.orderId) {
                        try { sessionStorage.setItem('sw_pay_order', data.orderId); } catch (err) { /* noop */ }
                    }
                    showMsg(personForm, 'Переход к оплате Т‑Банк…', false);
                    window.location.href = data.paymentUrl;
                }).catch(function () {
                    setLoading(personForm, false);
                    showMsg(personForm, 'Ошибка сети', true);
                });
            });
        }

        var companyForm = root.querySelector('#pay-company');
        if (companyForm) {
            bindValidationClear(companyForm);
            var innInput = companyForm.querySelector('[name="inn"]');
            var companyInput = companyForm.querySelector('[name="company"]');
            var kppInput = companyForm.querySelector('[name="kpp"]');
            var ogrnInput = companyForm.querySelector('[name="ogrn"]');
            var addressInput = companyForm.querySelector('[name="address"]');
            var partyBox = companyForm.querySelector('[data-pay-party]');
            var partyTimer = null;
            var lastPartyInn = '';

            function setPartyMeta(text, isError) {
                if (!partyBox) return;
                partyBox.hidden = !text;
                partyBox.textContent = text || '';
                partyBox.classList.toggle('is-error', !!isError);
            }

            function fillParty(party) {
                if (!party) return;
                if (companyInput && party.name) companyInput.value = party.name;
                if (kppInput) kppInput.value = party.kpp || '';
                if (ogrnInput) ogrnInput.value = party.ogrn || '';
                if (addressInput) addressInput.value = party.address || '';
                var bits = [];
                if (party.kpp) bits.push('КПП ' + party.kpp);
                if (party.ogrn) bits.push('ОГРН ' + party.ogrn);
                if (party.address) bits.push(party.address);
                setPartyMeta(bits.join(' · ') || 'Организация найдена', false);
                clearFieldError(companyForm, 'company');
            }

            function clearPartyExtras() {
                if (kppInput) kppInput.value = '';
                if (ogrnInput) ogrnInput.value = '';
                if (addressInput) addressInput.value = '';
                setPartyMeta('', false);
            }

            function lookupParty() {
                if (!innInput) return;
                var inn = String(innInput.value || '').replace(/\D+/g, '');
                if (inn.length !== 10 && inn.length !== 12) {
                    clearPartyExtras();
                    lastPartyInn = '';
                    return;
                }
                if (inn === lastPartyInn) return;
                lastPartyInn = inn;
                setPartyMeta('Ищем организацию…', false);
                post('find_party', { inn: inn }).then(function (data) {
                    if (!data.ok || !data.party) {
                        clearPartyExtras();
                        setPartyMeta(data.error || 'Организация не найдена', true);
                        return;
                    }
                    fillParty(data.party);
                }).catch(function () {
                    clearPartyExtras();
                    setPartyMeta('Не удалось связаться с DaData', true);
                });
            }

            if (innInput) {
                innInput.addEventListener('input', function () {
                    clearFieldError(companyForm, 'inn');
                    if (partyTimer) clearTimeout(partyTimer);
                    partyTimer = setTimeout(lookupParty, 450);
                });
                innInput.addEventListener('blur', lookupParty);
            }

            companyForm.addEventListener('submit', function (e) {
                e.preventDefault();
                var validation = validateCompanyForm(companyForm);
                if (!validation.ok) {
                    showValidationErrors(companyForm, validation.errors);
                    return;
                }
                showMsg(companyForm, '', false);
                clearFieldErrors(companyForm);
                setLoading(companyForm, true);
                post('invoice', {
                    inn: fieldVal(companyForm, 'inn'),
                    company: fieldVal(companyForm, 'company'),
                    kpp: fieldVal(companyForm, 'kpp'),
                    ogrn: fieldVal(companyForm, 'ogrn'),
                    address: fieldVal(companyForm, 'address'),
                    target: fieldVal(companyForm, 'target'),
                    years: String(yearsOf(companyForm)),
                    email: fieldVal(companyForm, 'email')
                }).then(function (data) {
                    setLoading(companyForm, false);
                    if (!data.ok) {
                        showMsg(companyForm, data.error || 'Не удалось выставить счёт', true);
                        return;
                    }
                    showMsg(companyForm, 'Счёт сформирован', false);
                    showResult(data.result || '');
                    if (data.url) {
                        window.open(data.url, '_blank', 'noopener');
                    }
                }).catch(function () {
                    setLoading(companyForm, false);
                    showMsg(companyForm, 'Ошибка сети', true);
                });
            });
        }
    }

    document.querySelectorAll('.pay').forEach(initPayRoot);

    // Клик по затемнению закрывает окно оплаты (не зависит от sw.js)
    if (payOverlay) {
        payOverlay.addEventListener('click', function (e) {
            if (e.target !== payOverlay) return;
            if (isPayModalOpen()) {
                e.preventDefault();
                e.stopPropagation();
                closePayModal();
            }
        });
    }
    if (payFormBox) {
        payFormBox.addEventListener('click', function (e) {
            e.stopPropagation();
        });
    }

    document.addEventListener('click', function (e) {
        if (e.target.closest('[data-pay-open]')) {
            e.preventDefault();
            openPayModal();
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && isPayModalOpen()) {
            closePayModal();
        }
    });

    window.openPayModal = openPayModal;
    window.closePayModal = closePayModal;
    window.isPayModalOpen = isPayModalOpen;

    // Возврат с Т‑Банка на holidays-calendar
    var params = new URLSearchParams(window.location.search);
    var orderId = params.get('order');
    if (!orderId) {
        try { orderId = sessionStorage.getItem('sw_pay_order'); } catch (err) { orderId = null; }
    }
    if (params.get('pay') === 'fail') {
        showResultGlobal('Оплата не завершена. Можно попробовать ещё раз.');
        return;
    }
    if (!orderId || !document.getElementById('pay-result')) return;

    var pollEndpoint = getEndpoint();
    showResultGlobal('Проверяем оплату и результат скрипта…');
    var tries = 0;
    var maxTries = 40;

    function poll() {
        tries += 1;
        postAction(pollEndpoint, 'pay_result', { order: orderId }).then(function (data) {
            if (!data.ok) {
                showResultGlobal(data.error || 'Заказ не найден');
                return;
            }
            if (data.status === 'done' || data.status === 'script_error') {
                showResultGlobal(data.result || ('Статус: ' + data.status));
                try { sessionStorage.removeItem('sw_pay_order'); } catch (err) { /* noop */ }
                return;
            }
            if (data.status === 'failed') {
                showResultGlobal('Оплата отклонена банком.');
                return;
            }
            if (tries >= maxTries) {
                showResultGlobal('Оплата ещё обрабатывается. Обновите страницу через минуту.\nЗаказ: ' + orderId);
                return;
            }
            showResultGlobal('Статус: ' + data.status + '\nОжидаем подтверждение и результат скрипта…');
            setTimeout(poll, 3000);
        }).catch(function () {
            if (tries < maxTries) setTimeout(poll, 3000);
            else showResultGlobal('Не удалось получить результат. Заказ: ' + orderId);
        });
    }
    poll();
})();
