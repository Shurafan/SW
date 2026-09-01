// Вешаем событие на прокрутку
window.modul = window.modul || [];
window.fon = window.fon || [];
window.ul = window.ul || [];
window.addEventListener('load',function () {
    var locked=false, a = document.querySelector('.region-left-content'),b = null, K = null, Z = 0, P = 80, N = 0;  // если у P ноль заменить на число, то блок будет прилипать до того, как верхний край окна браузера дойдёт до верхнего края элемента, если у N — нижний край дойдёт до нижнего края элемента. Может быть отрицательным числом
    window.addEventListener("scroll",listing,false);
    document.body.addEventListener('scroll', listing, false);
    function listing (e) {
        // Достигли конца страницы?
        var t=window.pageYOffset || document.documentElement.scrollTop;
        t+=window.innerHeight;
            var foot = document.querySelector('footer');
            var fh = foot ? foot.getBoundingClientRect().height : 0;
            var d=document.body.scrollHeight-window.innerHeight/2 - fh;
        if ((!locked) && (t >= d)) {
            locked=true;
            let data = new FormData();
            data.append('action', 'next');
            data.append('invet', window.location.pathname.replace(/^\/|\/$/g, ""));
            post('ajaxress', data, showResult);
        }

        var Ra = a.getBoundingClientRect(),
            R1b = document.querySelector('.region-content').getBoundingClientRect().bottom;
        if (Ra.bottom < R1b) { //когда не достигли низа
            if (b == null) { //создание примитива b
                var Sa = getComputedStyle(a, ''), s = '';
                for (var i = 0; i < Sa.length; i++) {
                    if (Sa[i].indexOf('overflow') == 0 || Sa[i].indexOf('padding') == 0 || Sa[i].indexOf('border') == 0 || Sa[i].indexOf('box-shadow') == 0) {
                        s += Sa[i] + ': ' +Sa.getPropertyValue(Sa[i]) + '; '
                    }
                }
                b = document.createElement('div');
                b.className = "stop";
                b.style.cssText = s + ' box-sizing: border-box; width: ' + a.offsetWidth + 'px;';
                a.insertBefore(b, a.firstChild);
                var l = a.childNodes.length;
                for (var i = 1; i < l; i++) {
                    b.appendChild(a.childNodes[1]);
                }
                a.style.height = b.getBoundingClientRect().height + 'px';
                a.style.padding = '0';
                a.style.border = '0';
            }
            var Rb = b.getBoundingClientRect(),//размеры пустышки
                Rh = Ra.top + Rb.height,//нижний край пустышки
                W = document.documentElement.clientHeight,//высота экрана
                R1 = Math.round(Rh - R1b),//растояние до низа в 
                R2 = Math.round(Rh - W);//растояние ??
            if (Rb.height > W) {//если пустышка больше экрана
                if (Ra.top < K) {  // скролл вниз
                    if (R2 + N > R1) {  // не дойти до низа
                        if (Rb.bottom - W + N <= 0) {  // подцепиться
                            b.className = 'sticky';
                            b.style.top = W - Rb.height - N + 'px';
                            Z = N + Ra.top + Rb.height - W;
                        } else {
                            b.className = 'stop';
                            b.style.top = - Z + 'px';
                        }
                    } else {
                        b.className = 'stop';
                        b.style.top = - R1 +'px';
                        Z = R1;
                    }
                } else {  // скрол в верх
                    if (Ra.top - P < 0) {  // не дойти до верха
                        if (Rb.top - P >= 0) {  // подцепиться
                            b.className = 'sticky';
                            b.style.top = P + 'px';
                            Z = Ra.top - P;
                        } else {
                            b.className = 'stop';
                            b.style.top = - Z + 'px';
                        }
                    } else {
                        b.removeAttribute('class');
                        b.style.top = '';
                        Z = 0;
                    }
                }
                K = Ra.top;
            } else {//если пустышка меньше экранна
                if ((Ra.top - P) <= 0) {
                    if ((Ra.top - P) <= R1) {
                        b.style.top = - R1 +'px';
                        b.className = 'stop';
                    } else {
                        b.style.top = P + 'px';
                        b.className = 'sticky';
                    }
                } else {
                    b.removeAttribute('class');
                    b.style.top = '';
                }
            }
            window.addEventListener('resize', function() {
                a.children[0].style.width = getComputedStyle(a, '').width
            }, false);
        }
    }
    function showResult(e){
        if(e=='stop'){locked=true;
            console.log('Stop');}
        else var j=JSON.parse(e);
        if(!!j){
            var b=document.createElement('div'),a=document.createElement('div'),c=document.querySelector('.content'),r=new URL(window.location.href);
            b.className="field-body";
            b.innerHTML=j.CONT;// Встраиваем содержимое AJAX в DOM
            if (window.SwCodeSandbox) window.SwCodeSandbox.scan(b);
            var scripts = Array.from(b.getElementsByTagName('script'));
            for (let oldScript of scripts) {
                var newScript = document.createElement('script');
                newScript.text = oldScript.text;
                // Заменяем старый скрипт новым
                oldScript.parentNode.replaceChild(newScript, oldScript);
            }
            
            a.className="anons";
            a.innerHTML=j.ANONS;
            c.appendChild(a);
            c.appendChild(b);
            if (typeof bindFaqAccordion === 'function') bindFaqAccordion(b);
            r.pathname=j.URL;
            history.pushState(null, null, r);
            window.fon.push(j.FON);
            if (typeof window.preloadPageBackground === 'function') {
                window.preloadPageBackground(j.FON);
            }
            window.ul.push(window.location.pathname.toString().slice(1));
            heit();
            locked=false;
        }
    }
    heit();
},false);
var par1=0;
window.addEventListener('resize',heit,false);
window.addEventListener('scroll',listFon,false);
// вспомогательное resaiz высота элементов Фон
function heit(){
    var re=document.querySelector('.region-content .content');
    if (!re) return;
    var res=100, rel = re.children;
    window.modul= [];
    for (let i = 0; i < rel.length; i++) {
        res += rel[i].offsetHeight;
        window.modul.push(res);
    }
    listFon();
}
// вспомогательное листинг и изменение урл
function listFon() {
    if (!window.modul || window.modul.length < 2) return;
    if (!window.fon || !window.ul) return;
    let par=window.pageYOffset || document.documentElement.scrollTop;
    for (let i = 1; i <= window.modul.length - 1; i+=2) {
        if(window.modul[i]> par+ window.innerHeight) {
            if(par1!=i){
            par1=i;
            var activeItem = document.querySelector('.block-comenu .active');
            if (activeItem) activeItem.classList.remove('active');
            if (window.fon[(i-1)/2]) {
                if (typeof window.setPageBackground === 'function') {
                    window.setPageBackground(window.fon[(i-1)/2]);
                } else {
                    document.body.style.backgroundImage =
                        'url('+window.fon[(i-1)/2]+')';
                }
            }
            var menuLink = document.querySelector('.block-comenu a[href="'+window.ul[(i-1)/2]+'"]');
            if (menuLink) menuLink.parentNode.className = 'active';
            history.pushState(null, null, new URL(window.location.href).pathname=window.ul[(i-1)/2]);
            }
        break;
        }
    }
    
}
