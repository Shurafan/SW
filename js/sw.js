//логика//
topi=0;
time=true;
let ms = new Date();
menuL=0;
menuH=0;
let ow;
let f;
let test;
let t;

// Параметры анимации
let maxOffset = 15; // максимальное смещение в пикселях
let noiseIntensity = 1; // интенсивность "дрожания"
let nInt = 0.9 // порог случайности
const maxSpeed = 1000; // пикс/с — выше этой скорости угол не растёт
const maxAngle = 4;    // максимальный поворот в градусах

let mouseX = 0;
let mouseY = 0;
let noiseX = 0;
let noiseY = 0;

let lastX = 0;
let lastY = 0;
let lastTime = 0;
let rotationD = 0;

function wrapContentTables(root) {
    var scope = root && root.nodeType === 1 ? root : document;
    var tables = [];
    if (scope.tagName === 'TABLE') {
        tables = [scope];
    } else if (scope.classList && scope.classList.contains('field-body')) {
        tables = scope.querySelectorAll('table');
    } else {
        tables = scope.querySelectorAll('main .field-body table, .field-body table');
    }
    Array.prototype.forEach.call(tables, function (table) {
        if (!table || !table.closest || !table.closest('.field-body')) return;
        if (table.closest('.table-scroll, .pk-month, .pk-wrap, .pay')) return;
        var wrap = document.createElement('div');
        wrap.className = 'table-scroll';
        table.parentNode.insertBefore(wrap, table);
        wrap.appendChild(table);
    });
}
window.wrapContentTables = wrapContentTables;
function watchContentTables() {
    var target = document.querySelector('main') || document.body;
    if (!target || typeof MutationObserver === 'undefined') return;
    var timer = null;
    new MutationObserver(function () {
        if (timer) clearTimeout(timer);
        timer = setTimeout(function () { wrapContentTables(document); }, 50);
    }).observe(target, { childList: true, subtree: true });
}
function bindFaqAccordion(root) {
    var boxes = (root || document).querySelectorAll('.faq');
    Array.prototype.forEach.call(boxes, function (box) {
        if (!box.querySelector('details')) return;
        if (box.getAttribute('data-acc') === '1') return;
        box.setAttribute('data-acc', '1');
        box.addEventListener('click', function (e) {
            var sum = e.target.closest ? e.target.closest('summary') : null;
            if (!sum || !box.contains(sum)) return;
            var row = sum.parentElement;
            if (!row || row.tagName !== 'DETAILS') return;
            Array.prototype.forEach.call(box.querySelectorAll('details[open]'), function (open) {
                if (open !== row) open.removeAttribute('open');
            });
        });
    });
}
function bindFaqCurtain(root) {
    var boxes = (root || document).querySelectorAll('.block-plitka > .faq');
    Array.prototype.forEach.call(boxes, function (box) {
        if (box.getAttribute('data-curtain') === '1') return;
        var shade = box.querySelector('.faq-shade');
        var qEl = box.querySelector('.faq-q');
        var aEl = box.querySelector('.faq-a');
        if (!shade || !qEl || !aEl) return;
        box.setAttribute('data-curtain', '1');
        function closeShade() {
            box.classList.remove('open');
            shade.setAttribute('aria-hidden', 'true');
        }
        box.addEventListener('click', function (e) {
            if (e.target.closest && e.target.closest('.faq-back')) {
                closeShade();
                return;
            }
            var btn = e.target.closest ? e.target.closest('li > button') : null;
            if (!btn || !box.contains(btn)) return;
            var ans = btn.nextElementSibling;
            qEl.textContent = btn.textContent;
            aEl.innerHTML = ans ? ans.innerHTML : '';
            box.classList.add('open');
            shade.setAttribute('aria-hidden', 'false');
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && box.classList.contains('open')) closeShade();
        });
    });
}
window.addEventListener('load',function(){
    wrapContentTables(document);
    watchContentTables();
    bindFaqAccordion(document);
    bindFaqCurtain(document);
    [].forEach.call(document.querySelectorAll('.city'), i => {
        i.addEventListener('mouseover', zastavca, false);
        i.addEventListener('click', zastavca, false);
    });
    // стартовая карта по активному городу (Краснодар / Орёл)
    var activeCity = document.querySelector('.city.active') || document.querySelector('.city');
    if (activeCity) setContactsMap(activeCity);
    //после загрузки страницы
    m=document.querySelector('.menu');
    n=document.querySelector('nav');
    f=document.querySelector('.modal-form');
    ow= document.querySelector('.owerlei');
    //узнаем длину меню (меню может отсутствовать при сбое Wayfinder/кэша)
    if (m) {
        let el = m.children;
        for (let i = 0; i < el.length; i++) {menuL += el[i].offsetWidth;}
    }
    //узнаем высоту меню
    var headerEl = document.querySelector('header');
    menuH = headerEl ? headerEl.offsetHeight : 0;
    // меню для tablet или mobail
    if(m && n && menuL > document.body.clientWidth - 220){
        (document.body.clientWidth<=530)? sw_mobail('on'):n.classList.add("tabl");}
    if (m) setTimeout(function(){sw_menu(false);}, 7000);

    //if(!!window.fon) document.querySelector('body').style.backgroundImage = 'url('+window.fon[0]+')';
    if(!!window.fon && window.fon[0]) setPageBackground(window.fon[0]);
    //функции меню
    if (m) {
        m.onmouseout = ()=> ms= new Date();
        m.onscroll = updateTablArrows;
        if(n && n.classList.contains('tabl')) updateTablArrows();
    }
    // отправка форм
    if (document.mainform) document.mainform.addEventListener('submit',ModalW);
    if(!!document.futerform){document.futerform.addEventListener('submit',ModalW);}

    let p=document.querySelectorAll('.webform-main input,.webform-main textarea');
    for(let i = 0; i < p.length; i++) {
        p[i].onfocus = function() {if(this.previousElementSibling){this.previousElementSibling.style.top='0px';this.previousElementSibling.style.fontSize='12px';}this.parentNode.style.backgroundPosition='100% 100%';}
        p[i].onblur = function() {if(!this.value) {if(this.previousElementSibling) this.previousElementSibling.removeAttribute("style");
            this.parentNode.removeAttribute("style");}
        }}

    if (ow) ow.onclick = function (e) {
        if (e && e.target !== ow) return;
        if (typeof window.isPayModalOpen === 'function' && window.isPayModalOpen()) {
            if (typeof window.closePayModal === 'function') window.closePayModal();
            return;
        }
        var payEl = document.getElementById('pay-modal-form');
        if (payEl && (payEl.style.display === 'block' || payEl.classList.contains('is-open'))) {
            if (typeof window.closePayModal === 'function') window.closePayModal();
            return;
        }
        closeModal();
    };
    function toggleProjectModal() {
        if (!ow) return;
        (ow.style.display == 'block')? closeModal():showModal();
    }
    var projectBtn = document.querySelector('#project');
    if (projectBtn) projectBtn.onclick = toggleProjectModal;
    let projectMenu = document.querySelector('#project-menu');
    if (projectMenu) {
        projectMenu.onclick = function() {
            if (n && n.classList.contains('mobail')) resetMobailPopup();
            toggleProjectModal();
        };
    }
    bindProjectStart();

    let fil=document.querySelectorAll('input[type="file"]');
    for(let i = 0; i < fil.length; i++) {
        fil[i].onchange = function(){
            let tex = this.value.split('\\');
            this.parentElement.previousElementSibling.innerHTML = tex[tex.length-1];
    }}
    
    sail = document.querySelector('.kait');

    // Анимация с шумом
    function animate() {
      if (!sail) return;
      if(nInt < Math.random()) {
        noiseX = (Math.random() - 0.5) * noiseIntensity;
        noiseY = (Math.random() - 0.5) * noiseIntensity;
      }
      // Нормализуем положение мыши к диапазону [-1, 1]
      let normX = mouseX / (window.innerWidth / 2);
      let normY = mouseY / (window.innerHeight / 2);
  
      let moveX = normX * maxOffset + noiseX;
      let moveY = normY * maxOffset + noiseY;
      let rect = sail.getBoundingClientRect().left + window.scrollX;
      let rotation = ((-normX + normY) / 2) * 2; // от -2 до +2 градусов
      // sail.style.backgroundPosition = `${rect + moveX}px ${menuH + moveY}px`;
      
      // Применяем transform к ::before через CSS-переменные (лучший способ)
      sail.style.setProperty('--rotate', `${rotation + rotationD}deg`);
      sail.style.setProperty('--translate-x', `${rect + moveX}px`);
      sail.style.setProperty('--translate-y', `${menuH + moveY}px`);
    
      window.requestAnimationFrame(animate);
    }

    if (sail) {
      document.addEventListener('mousemove', (e) => {
        mouseX = e.clientX - window.innerWidth / 2;
        mouseY = e.clientY - window.innerHeight / 2;

        let now = performance.now();
        let deltaX = e.clientX - lastX;
        let deltaY = e.clientY - lastY;
        let deltaTime = now - lastTime;
        if (deltaTime > 0 && lastTime > 0) {
          const speedX = deltaX / deltaTime * 1000;
          const speedY = deltaY / deltaTime * 1000;
          const combinedSpeed = (-speedX + speedY);
          const normSpeed = Math.max(-1, Math.min(1, combinedSpeed / maxSpeed));
          const targetRotation = normSpeed * maxAngle;
          rotationD = rotationD * 0.7 + targetRotation * 0.3;
        }
        lastX = e.clientX;
        lastY = e.clientY;
        lastTime = now;
      });
      animate();
    }

},false);

window.addEventListener('mousemove',function (e) {
    // console.log(document.documentElement.clientWidth - e.clientX)
    //мышь главное меню
    if(e.clientY < window.menuH && document.documentElement.clientWidth > 610 && e.clientX < document.documentElement.clientWidth - 200){sw_menu(true);}},false); // 530

window.addEventListener('resize',function() {
    if (!n || !m) return;
    // сброс открытого мобильного меню при любом ресайзе
    resetMobailPopup();
    var headerEl = document.querySelector('header');
    menuH = headerEl ? headerEl.offsetHeight : 0;

    if(document.body.clientWidth> 220 + menuL){
        if(n.classList.contains("tabl")){
            var rightBtn = document.querySelector(".right");
            var leftBtn = document.querySelector(".left");
            if (rightBtn) rightBtn.removeAttribute("style");
            if (leftBtn) leftBtn.removeAttribute("style");
            n.classList.remove("tabl");
            n.classList.remove("bleft");
            n.classList.remove("bright");
        }
        if(n.classList.contains("mobail")) sw_mobail('off');
    }else {
        if(document.body.clientWidth<=530){
            if(n.classList.contains("tabl")) {
                (n.classList.length>1)? n.classList.remove("tabl"): n.removeAttribute('class');
                document.querySelector(".right").removeAttribute("style");
                document.querySelector(".left").removeAttribute("style");
                n.classList.remove("bleft");
                n.classList.remove("bright");
            }
            if(!n.classList.contains("mobail")) sw_mobail('on');
        }else{
            if(n.classList.contains("mobail")) sw_mobail('off');
            if(!n.classList.contains("tabl")) n.classList.add("tabl");
            updateTablArrows();
        }
    }
},false);
window.addEventListener('scroll',function() {
    let top = window.pageYOffset || document.documentElement.scrollTop;
    //анимация главного меню
    if(top < window.topi - window.menuH &&  !window.time) {
        sw_menu(true);
        topi = top;
        setTimeout(function(){sw_menu(false);}, 6000);
    }
    if (top > topi || time) topi = top;
    // затенение хейдера
    var headerEl = document.querySelector('header');
    if (headerEl) {
        headerEl.style.background = (top>30) ? 'rgba(104, 104, 104, 0.9)' : 'none';
    }
},false);
//анимация меню
function sw_menu(param){
    if (!n) return;
    var sec = new Date() - ms;
    if(time && sec <= 6000){
        setTimeout(function(){sw_menu(false);}, sec);
        return;
    }
    if(n.classList.contains("mobail")) {
        time=true;
        // не трогаем открытую панель; закрытую оставляем спрятанной по CSS
        if (!document.querySelector('.gamburger.krest')) hideMobailNav();
        p1="-"+window.menuH+"px";
    }else if(param) {
        time=true;
        n.removeAttribute('style');
        p1="-"+window.menuH+"px";
    }else{
        time=false;
        p1="0px";
        n.style.top="-"+window.menuH+"px";
    }
    var slogan = document.querySelector('.slogan');
    var contact = document.querySelector('.contact');
    if (slogan) slogan.style.top = p1;
    if (contact) contact.style.top = p1;
    (document.documentElement.clientWidth<1099)? document.querySelector('.redbtn').style.top = p1:document.querySelector('.redbtn').style.top = "0px";
}
function left(){if(m) m.scrollLeft=0;}
function right(){if(m) m.scrollLeft=580;}
function updateTablArrows(){
    if(!n || !m || !n.classList.contains('tabl')) return;
    var leftBtn = document.querySelector('.left');
    var rightBtn = document.querySelector('.right');
    if(!leftBtn || !rightBtn) return;
    if(m.scrollLeft > 20){
        leftBtn.style.display = 'block';
        n.classList.remove('bleft');
    }else{
        leftBtn.style.display = 'none';
        n.classList.add('bleft');
    }
    if(menuL - m.offsetWidth - 20 > m.scrollLeft){
        rightBtn.style.display = 'block';
        n.classList.remove('bright');
    }else{
        rightBtn.style.display = 'none';
        n.classList.add('bright');
    }
}
// спрятать мобильное меню: нижний край на 1 строку выше 0
function hideMobailNav(){
    if (!n || !n.classList.contains('mobail')) return;
    var line = parseInt(getComputedStyle(document.documentElement).getPropertyValue('--header-heit'), 10) || 60;
    var h = m.scrollHeight || n.scrollHeight;
    n.style.top = -(h + line) + 'px';
}
function showMobailNav(){
    if (!n) return;
    n.style.top = window.menuH + 'px';
}
// сброс всплытия мобильного меню (закрыть, если открыто)
function resetMobailPopup(){
    var param1 = document.querySelector('.gamburger');
    if(param1 && param1.classList.contains('krest')){
        param1.classList.remove('krest');
        hideMobailNav();
    }
}
// меню маленькое
function sw_mobail(param){
    if (param === "on") {
        if (n.classList.contains("mobail")) return;
        n.classList.add("mobail");
        gamburger = document.createElement('a');
        gamburger.href = '#';
        gamburger.className = 'gamburger-btn';
        gamburger.setAttribute('aria-label', 'Меню');
        gamburger.innerHTML = '<div class="gamburger"><div class="lineM"></div><div class="lineM"></div><div class="lineM"></div></div>';
        gamburger.addEventListener('click', function(e){
            e.preventDefault();
            sw_mobail('click');
        });
        document.body.appendChild(gamburger);
        hideMobailNav();
    }
    if(param === "off"){
        if (!n.classList.contains("mobail")) return;
        resetMobailPopup();
        n.classList.remove("mobail");
        if (typeof gamburger !== 'undefined' && gamburger && gamburger.parentNode) {
            gamburger.parentNode.removeChild(gamburger);
        } else {
            var gLink = document.querySelector('a.gamburger-btn');
            if (gLink) gLink.parentNode.removeChild(gLink);
        }
        gamburger = null;
        n.removeAttribute('style');
        sw_menu(false);
    }
    if(param === "click"){
        var param1=document.querySelector('.gamburger');
        if(!param1) return;
        if(param1.classList.contains("krest")){
            param1.classList.remove("krest");
            hideMobailNav();
        }else{
            param1.classList.add("krest");
            showMobailNav();
        }
    }
}
// Прокрутка вниз на экран
function down(heiOl = 0){
    let hei= window.pageYOffset || document.documentElement.scrollTop;
    let cli = document.documentElement.clientHeight;
    // console.log(hei+" - "+cli+" - "+heiOl);
    if(cli>hei && (heiOl === 0 || hei !== heiOl)){
        window.scrollBy(0,((cli-hei)/16)+4);
        t= setTimeout(() => down(hei), 10);
    }else clearTimeout(t);
    return false;
}
//всплывающее меню
function syncModalOverlay() {
    if (!ow) return;
    var payEl = document.getElementById('pay-modal-form');
    var payOpen = payEl && payEl.style.display === 'block';
    var projOpen = f && f.style.display === 'block';
    if (!payOpen && !projOpen) {
        ow.style.background = '#0000';
        ow.style.display = 'none';
        document.body.style.overflow = 'initial';
        document.body.style.width = 'auto';
    }
}
function showModal(){
    if (!ow || !f) return;
    var payEl = document.getElementById('pay-modal-form');
    if (payEl) payEl.style.display = 'none';
    ow.style.display='block';
    ow.style.background='#0009';
    f.style.display='block';
    var w = document.body.offsetWidth;
    document.body.style.overflow = 'hidden';
    document.body.style.width = w + 'px';
}
function closeModal(){
    if (!ow || !f) return;
    f.style.display='none';
    syncModalOverlay();
}
function fillProjectDraft(text) {
    if (!text) return;
    var areas = document.querySelectorAll('textarea[name="projectUser"]');
    for (var i = 0; i < areas.length; i++) {
        var ta = areas[i];
        ta.value = text;
        if (ta.previousElementSibling) {
            ta.previousElementSibling.style.top = '0px';
            ta.previousElementSibling.style.fontSize = '12px';
        }
        if (ta.parentNode) {
            ta.parentNode.style.backgroundPosition = '100% 100%';
        }
    }
}
function bindProjectStart() {
    if (document.documentElement.getAttribute('data-start') === '1') return;
    document.documentElement.setAttribute('data-start', '1');
    document.addEventListener('click', function (e) {
        var el = e.target && e.target.closest
            ? e.target.closest('.start[data-project]') : null;
        if (!el) return;
        e.preventDefault();
        fillProjectDraft(el.getAttribute('data-project'));
        showModal();
    });
}
function ModalW(e) {
    e.preventDefault();
    this.elements.op.classList.add("onclic");
    this.elements.op.value="";
    var error = 0;
    var nU=this.elements.nameUser;
    nU.className = '';
    if (nU.value.length<3 || nU.value.length>30){error++;nU.className = 'error';}
    var cU=this.elements.contactUser;
    cU.className = '';
    if (!cU.value){
        error++;
        cU.className = 'error';
    }
    window.test=this.elements.filesUser.files[0];
    //if(!document.getElementById("check").checked){error++;}
    if (error === 0){
        this.removeEventListener('submit',ModalW);
        let data = new FormData();
        data.append('invet', window.location.pathname.replace(/\//g, ""));
        data.append('name', nU.value);
        data.append('contact', cU.value);
        data.append('action', 'stendMail');
        data.append('message', this.elements.projectUser.value);
        data.append('mail', this.elements.emailUser.value);
        data.append('file', this.elements.filesUser.files[0]);
        post("ajaxress",data,webform, this);
    }
    return false;
}
// Отправка почты
function post(u,a,f,o){
    fetch(u,
        {
            method: "POST",
            body: a
        })
        .then( (response) => {
            if (response.status !== 200) {
                return Promise.reject();
            }
            return response.text()
        })
        .then(i => f(i,o))
        .catch(() => f("Ошибка сервера",o));
}
// Анимация отправки письма
function webform(e,b){
    if (e){
        console.log(e);
        if(e === "success"){
            b.elements.op.classList.add('validate');
            b.elements.op.classList.remove('onclic');
            b.elements.op.parentElement.classList.add('form-stop');
            setTimeout(function(){b.elements.op.parentElement.classList.add('form-stop-ok');},900);
            setTimeout(closeModal, 8000);
        }else{
            b.elements.op.classList.remove('onclic');
            b.elements.op.value="Упс. Не ушло";
            setTimeout(function(){b.elements.op.value="Отправить";b.addEventListener('submit',ModalW);}, 8000);
        }
    }
}
// Фон страницы через fixed-слой (работает на iOS)
function ensurePageBgLayer() {
    var el = document.getElementById('sw-page-bg');
    if (!el) {
        el = document.createElement('div');
        el.id = 'sw-page-bg';
        el.setAttribute('aria-hidden', 'true');
        if (document.body.firstChild) {
            document.body.insertBefore(el, document.body.firstChild);
        } else {
            document.body.appendChild(el);
        }
    }
    return el;
}
var pageBgShown = '';
var pageBgWanted = '';
function resolvePageBgUrl(url) {
    var abs = String(url || '').trim();
    if (!abs) return '';
    try {
        abs = new URL(abs, document.baseURI || window.location.href).href;
    } catch (e) {}
    return abs;
}
function cssBgUrl(abs) {
    return 'url("' + String(abs).replace(/\\/g, '\\\\').replace(/"/g, '\\"') + '")';
}
function preloadPageBackground(url) {
    var abs = resolvePageBgUrl(url);
    if (!abs) return;
    var img = new Image();
    img.src = abs;
}
function setPageBackground(url) {
    var abs = resolvePageBgUrl(url);
    if (!abs || abs === pageBgWanted) return;
    pageBgWanted = abs;
    var el = ensurePageBgLayer();
    var want = cssBgUrl(abs);
    var shown = pageBgShown ? cssBgUrl(pageBgShown) : '';
    el.style.backgroundImage = shown && shown !== want
        ? want + ', ' + shown : want;
    var img = new Image();
    img.onload = function () {
        if (pageBgWanted !== abs) return;
        pageBgShown = abs;
        el.style.backgroundImage = want;
    };
    img.src = abs;
}
window.setPageBackground = setPageBackground;
window.preloadPageBackground = preloadPageBackground;
// Офисы: одна метка с подписью «Студия West», координаты меняются вместе с городом
var contactsOffices = {
    'pic/krasnodar.jpg': { coords: [45.113695, 38.929118], zoom: 16 },
    'pic/orel.jpg': { coords: [53.00161072453392, 36.13715638741303], zoom: 15 }
};
var contactsMapState = { map: null, placemark: null, loading: false, pending: null, ymapsFailed: false };
var contactsMaps = {
    'pic/krasnodar.jpg': 'https://yandex.ru/map-widget/v1/?ll=38.929118%2C45.113695&z=16&pt=38.929118,45.113695,pm2orm',
    'pic/orel.jpg': 'https://yandex.ru/map-widget/v1/?um=constructor%3A14cdf27edfdb5804a50480b6b9be70572fc6160eec149b97bf8f18eaceb683c0&source=constructor'
};
function resolveContactsOffice(cityEl) {
    var key = (cityEl && cityEl.dataset && cityEl.dataset.url) || '';
    var office = contactsOffices[key];
    if (!office && cityEl) {
        var title = (cityEl.textContent || '').toUpperCase();
        if (title.indexOf('КРАСНОДАР') !== -1) office = contactsOffices['pic/krasnodar.jpg'];
        else if (title.indexOf('ОРЕЛ') !== -1 || title.indexOf('ОРЁЛ') !== -1) office = contactsOffices['pic/orel.jpg'];
    }
    return office || null;
}
function resolveContactsMapUrl(cityEl) {
    if (!cityEl) return '';
    var key = (cityEl.dataset && cityEl.dataset.url) || '';
    var mapUrl = contactsMaps[key] || '';
    if (!mapUrl) {
        var title = (cityEl.textContent || '').toUpperCase();
        if (title.indexOf('КРАСНОДАР') !== -1) mapUrl = contactsMaps['pic/krasnodar.jpg'];
        else if (title.indexOf('ОРЕЛ') !== -1 || title.indexOf('ОРЁЛ') !== -1) mapUrl = contactsMaps['pic/orel.jpg'];
    }
    return mapUrl || '';
}
function findContactsMapEl() {
    return document.getElementById('contacts-map')
        || document.querySelector('.region-content iframe[src*="yandex.ru/map-widget"]')
        || document.querySelector('.content iframe[src*="map-widget"]')
        || document.querySelector('iframe[src*="yandex.ru/map-widget"]');
}
function setContactsMapIframe(mapUrl) {
    if (!mapUrl) return;
    var iframe = findContactsMapEl();
    if (!iframe) return;
    var next = document.createElement('iframe');
    next.id = 'contacts-map';
    next.setAttribute('width', '100%');
    next.setAttribute('height', '370');
    next.setAttribute('frameborder', '0');
    next.setAttribute('allowfullscreen', 'true');
    next.style.display = 'block';
    next.style.border = '0';
    next.src = mapUrl;
    if (iframe.parentNode) iframe.parentNode.replaceChild(next, iframe);
}
function ensureContactsMapBox() {
    var el = findContactsMapEl();
    if (!el) return null;
    if (el.tagName === 'IFRAME') {
        var box = document.createElement('div');
        box.id = 'contacts-map';
        box.style.width = '100%';
        box.style.height = (el.getAttribute('height') || 370) + 'px';
        box.style.display = 'block';
        el.parentNode.replaceChild(box, el);
        return box;
    }
    if (!el.style.height) el.style.height = '370px';
    if (!el.style.width) el.style.width = '100%';
    return el;
}
function loadYmaps(cb, onError) {
    if (window.ymaps && window.ymaps.Map) {
        window.ymaps.ready(cb);
        return;
    }
    if (!loadYmaps._callbacks) loadYmaps._callbacks = [];
    loadYmaps._callbacks.push(cb);
    if (loadYmaps._started) return;
    loadYmaps._started = true;
    var s = document.createElement('script');
    s.src = 'https://api-maps.yandex.ru/2.1/?lang=ru_RU';
    s.async = true;
    s.onload = function () {
        if (!window.ymaps) {
            if (onError) onError();
            return;
        }
        window.ymaps.ready(function () {
            var list = loadYmaps._callbacks || [];
            loadYmaps._callbacks = [];
            list.forEach(function (fn) { fn(); });
        });
    };
    s.onerror = function () {
        loadYmaps._callbacks = [];
        if (onError) onError();
    };
    document.head.appendChild(s);
}
function applyContactsOffice(office) {
    if (!contactsMapState.map || !contactsMapState.placemark || !office) return;
    contactsMapState.placemark.geometry.setCoordinates(office.coords);
    contactsMapState.map.setCenter(office.coords, office.zoom);
}
function initContactsYMap(office) {
    var box = ensureContactsMapBox();
    if (!box || !office) return;
    if (contactsMapState.map) {
        applyContactsOffice(office);
        return;
    }
    contactsMapState.map = new ymaps.Map(box, {
        center: office.coords,
        zoom: office.zoom,
        controls: ['zoomControl', 'geolocationControl']
    }, { suppressMapOpenBlock: true });
    contactsMapState.placemark = new ymaps.Placemark(office.coords, {
        iconCaption: 'Студия West',
        hintContent: 'Студия West'
    }, {
        preset: 'islands#orangePocketIcon',
        iconColor: '#ff931e',
        iconCaptionMaxWidth: 200
    });
    contactsMapState.map.geoObjects.add(contactsMapState.placemark);
}
function setContactsMap(cityEl) {
    var office = resolveContactsOffice(cityEl);
    if (!office) return;
    if (contactsMapState.ymapsFailed) {
        setContactsMapIframe(resolveContactsMapUrl(cityEl));
        return;
    }
    contactsMapState.pending = office;
    if (contactsMapState.map) {
        applyContactsOffice(office);
        return;
    }
    if (contactsMapState.loading) return;
    contactsMapState.loading = true;
    loadYmaps(function () {
        contactsMapState.loading = false;
        try {
            initContactsYMap(contactsMapState.pending || office);
        } catch (err) {
            contactsMapState.ymapsFailed = true;
            setContactsMapIframe(resolveContactsMapUrl(cityEl));
        }
    }, function () {
        contactsMapState.loading = false;
        contactsMapState.ymapsFailed = true;
        setContactsMapIframe(resolveContactsMapUrl(cityEl));
    });
}
function zastavca(e) {
    setPageBackground(this.dataset.url);
    [].forEach.call(document.querySelectorAll('.city'), i => {
        i.classList.remove('active');
    });
    this.classList.add('active');
    setContactsMap(this);
}
