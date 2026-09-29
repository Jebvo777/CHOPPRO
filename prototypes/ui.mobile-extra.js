  function mobileQr(){return phoneChrome('QR сканер','КПП-1 · наведите камеру',`<div class="qr-box"><div class="qr-frame"></div></div><p style="text-align:center;font-size:10px;color:var(--muted)">Код должен находиться внутри рамки. QR-токен и его версия будут проверены сервером.</p><button class="m-btn" data-nav="mobile:checkin">QR считан · продолжить</button>`,'shift')}
  function mobilePatrol(){return phoneChrome('Обход','Маршрут «Утренний»',`<div class="m-card"><div style="display:flex;justify-content:space-between"><div><h3>3 контрольные точки</h3><p>10:00–10:30 · БЦ Север</p></div>${pill('1 / 3','info')}</div><div class="progress"><span style="width:33%"></span></div></div>${mini('check','01 · Холл','10:04 · QR valid · 5 м','success')}${mini('qr','02 · Паркинг','Следующая точка · 120 м','info')}${mini('qr','03 · Техзона','Ожидается','') }<button class="m-btn" data-nav="mobile:qr">Сканировать точку 02</button>`,'patrol')}
  function mobileIncident(){return phoneChrome('Происшествие','Новое событие',`<div class="field"><label>Категория</label><select><option>Нарушение режима</option><option>Медицинский случай</option><option>Техническая проблема</option></select></div><div class="field" style="margin-top:9px"><label>Серьезность</label><select><option>HIGH</option><option>CRITICAL</option><option>MEDIUM</option></select></div><div class="field" style="margin-top:9px"><label>Описание</label><textarea placeholder="Что произошло и какие меры приняты?">Попытка прохода по недействующему пропуску. Доступ остановлен.</textarea></div><div class="m-actions" style="margin-top:10px"><div class="m-action">${ico('camera')}Добавить фото</div><div class="m-action">${ico('mic')}Записать аудио</div></div><button class="m-btn" style="margin-top:11px" data-toast="Происшествие отправлено">Отправить происшествие</button>`,'home')}
  function mobileOffline(){return phoneChrome('Синхронизация','Очередь офлайн-событий',`${notice('<strong>3 события ожидают синхронизации.</strong> Исходное время устройства сохранено.','warning')}<div style="height:10px"></div>${mini('check','CHECK_IN','client 07:58 · pending sync','warning')}${mini('qr','CHECKPOINT_SCAN','client 08:14 · pending sync','warning')}${mini('alert','INCIDENT','client 08:21 · pending sync','warning')}<button class="m-btn" style="margin-top:12px" data-toast="Синхронизация запущена">Повторить синхронизацию</button>`, 'home', true)}
  function mobileNotifications(){return phoneChrome('События','Операционные уведомления',`${mini('route','Обход через 15 минут','Маршрут «Утренний» · 09:45','info')}${mini('check','Check-in подтвержден','КПП-1 · 07:51','success')}${mini('file','Новая инструкция поста','Версия 3.1 · вчера','info')}`,'notifications')}
  function mobileProfile(){return phoneChrome('Профиль','Иванов Иван',`<div class="m-card" style="text-align:center"><div class="detail-avatar" style="margin:0 auto 9px">ИИ</div><h3>Иванов Иван Иванович</h3><p>Охранник 4 разряда · №00421</p></div><div class="m-card"><h3>Устройство</h3><p>iPhone 15 · iOS 19 · зарегистрировано 21.09.2026</p>${pill('TRUSTED','success')}</div><div class="m-card"><h3>Согласия</h3>${mini('check','Обработка данных','Принято 21.09.2026','success')}${mini('pin','Геолокация в рабочем сценарии','Разрешено','success')}</div><button class="m-btn secondary" data-toast="Сессия завершена">Выйти</button>`,'profile')}
  function renderMobile(id){if(id==='login'||id==='otp')return mobileLogin(id==='otp');const c=id==='home'?mobileHome():id==='shift'?mobileShift():id==='instruction'?mobileInstruction():id==='checkin'?mobileCheckin(false):id==='checkresult'?mobileCheckin(true):id==='qr'?mobileQr():id==='patrol'?mobilePatrol():id==='incident'?mobileIncident():id==='offline'?mobileOffline():id==='notifications'?mobileNotifications():mobileProfile();return `<div class="proto-root"><div class="proto-frame">${topBar('mobile')}<div style="padding:20px 28px 34px"><div class="page-head"><div><h1>${screenIndex[`mobile:${id}`]?.title||'Мобильное приложение'}</h1><p>React Native · отдельные сборки Android и iOS · светлая тема.</p></div><div class="page-actions">${btn('Все экраны','layers')}</div></div>${c}</div></div></div>`}

  function screenMap(){
    const groups=Object.entries(APPS).map(([app,m])=>{const screens=allScreens().filter(s=>s.app===app);return `<section class="screen-group"><div class="screen-group-head"><h2>${m.label}</h2><span class="tag primary">${screens.length} экранов</span></div><div class="screen-cards">${screens.map(s=>`<article class="screen-card" data-nav="${app}:${s.id}"><div class="screen-ico">${ico(s.icon)}</div><strong>${s.title}</strong><span>${s.group}</span></article>`).join('')}</div></section>`}).join('');
    return `<div class="proto-root"><div class="proto-frame">${topBar('map')}<main class="screen-map"><section class="map-hero"><div><span class="tag" style="background:rgba(255,255,255,.16);color:#fff">UI/UX · Light</span><h1>Все окна ЧОППРО</h1><p>Единая дизайн-система для административного web-приложения, мобильных приложений Android/iOS, кабинета заказчика и системного контура платформы.</p></div><div class="count">${allScreens().length}</div></section>${groups}</main></div></div>`;
  }

  function render(){
    const {app,screen}=currentRoute();
    let html=app==='map'?screenMap():app==='admin'?renderAdmin(screen):app==='mobile'?renderMobile(screen):app==='client'?renderClient(screen):app==='platform'?renderPlatform(screen):screenMap();
    $('#app').innerHTML=html;
    bind();
  }

  function bind(){
    $$('[data-nav]').forEach(el=>el.addEventListener('click',e=>{e.preventDefault();const [a,s]=el.dataset.nav.split(':');go(a,s)}));
    $$('[data-open]').forEach(el=>el.addEventListener('click',()=>{const [a,s]=el.dataset.open.split(':');go(a,s)}));
    const sw=$('[data-app-switch]');if(sw)sw.addEventListener('change',()=>go(sw.value,sw.value==='mobile'?'home':sw.value==='platform'?'tenants':'dashboard'));
    $$('[data-toast]').forEach(el=>el.addEventListener('click',e=>{if(el.dataset.nav)return;e.preventDefault();toast(el.dataset.toast||'Готово')}));
    $$('[data-toggle]').forEach(t=>t.addEventListener('click',()=>t.classList.toggle('on')));
    const cmd=$('[data-command]');if(cmd)cmd.addEventListener('click',openCommand);
    document.onkeydown=e=>{if((e.metaKey||e.ctrlKey)&&e.key.toLowerCase()==='k'){e.preventDefault();openCommand()} if(e.key==='Escape')closeModal()};
  }

  function toast(text){const t=$('#toast');t.textContent=text;t.classList.add('show');clearTimeout(window.__toast);window.__toast=setTimeout(()=>t.classList.remove('show'),2200)}
  function closeModal(){const m=$('#modal');m.classList.remove('open');$('#modalBody').innerHTML=''}
  function openCommand(){
    const items=allScreens();
    $('#modalBody').innerHTML=`<div class="command"><h3>Перейти к экрану</h3><p>Найдите модуль или окно.</p><input id="cmdSearch" autofocus placeholder="Например: сотрудники, check-in, отчеты..."><div class="command-list" id="cmdList">${items.map(s=>`<div class="command-item" data-cmd="${s.app}:${s.id}">${ico(s.icon)}<strong>${s.title}</strong><span>${APPS[s.app].label}</span></div>`).join('')}</div></div>`;
    $('#modal').classList.add('open');
    const input=$('#cmdSearch');setTimeout(()=>input.focus(),20);
    input.addEventListener('input',()=>{const q=input.value.toLowerCase();$$('[data-cmd]').forEach(x=>x.classList.toggle('hide',!x.textContent.toLowerCase().includes(q))) });
    $$('[data-cmd]').forEach(x=>x.addEventListener('click',()=>{const [a,s]=x.dataset.cmd.split(':');closeModal();go(a,s)}));
    $('#modal').onclick=e=>{if(e.target.id==='modal')closeModal()};
  }

  render();
