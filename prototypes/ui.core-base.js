'use strict';

  const $ = (s, r=document) => r.querySelector(s);
  const $$ = (s, r=document) => [...r.querySelectorAll(s)];

  const iconPaths = {
    home:'<path d="M3 11.5 12 4l9 7.5"/><path d="M5 10.5V21h14V10.5"/><path d="M9 21v-6h6v6"/>',
    dashboard:'<rect x="3" y="3" width="7" height="7" rx="2"/><rect x="14" y="3" width="7" height="7" rx="2"/><rect x="3" y="14" width="7" height="7" rx="2"/><rect x="14" y="14" width="7" height="7" rx="2"/>',
    users:'<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
    user:'<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
    file:'<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M8 13h8M8 17h6"/>',
    shield:'<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/><path d="m9 12 2 2 4-4"/>',
    building:'<path d="M3 21h18"/><path d="M5 21V4h10v17"/><path d="M15 8h4v13"/><path d="M8 8h4M8 12h4M8 16h4"/>',
    map:'<path d="M3 6l6-3 6 3 6-3v15l-6 3-6-3-6 3V6Z"/><path d="M9 3v15M15 6v15"/>',
    pin:'<path d="M20 10c0 5-8 12-8 12S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/>',
    calendar:'<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/>',
    clock:'<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
    route:'<circle cx="6" cy="19" r="2"/><circle cx="18" cy="5" r="2"/><path d="M8 19h4a3 3 0 0 0 3-3V8a3 3 0 0 1 3-3"/>',
    alert:'<path d="M10.3 2.9 1.8 17a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 2.9a2 2 0 0 0-3.4 0Z"/><path d="M12 9v4M12 17h.01"/>',
    chart:'<path d="M3 3v18h18"/><path d="M7 16v-5M12 16V8M17 16V5"/>',
    audit:'<path d="M4 4h16v16H4z"/><path d="M8 9h8M8 13h8M8 17h5"/>',
    settings:'<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1-2.8 2.8-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.6V21H10v-.1a1.7 1.7 0 0 0-1-1.6 1.7 1.7 0 0 0-1.9.3l-.1.1L4.2 17l.1-.1a1.7 1.7 0 0 0 .3-1.9A1.7 1.7 0 0 0 3 14H3v-4h.1a1.7 1.7 0 0 0 1.6-1 1.7 1.7 0 0 0-.3-1.9L4.2 7 7 4.2l.1.1A1.7 1.7 0 0 0 9 4.6a1.7 1.7 0 0 0 1-1.6V3h4v.1a1.7 1.7 0 0 0 1 1.6 1.7 1.7 0 0 0 1.9-.3l.1-.1L19.8 7l-.1.1a1.7 1.7 0 0 0-.3 1.9 1.7 1.7 0 0 0 1.6 1h.1v4H21a1.7 1.7 0 0 0-1.6 1Z"/>',
    bell:'<path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/><path d="M10 21h4"/>',
    search:'<circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/>',
    plus:'<path d="M12 5v14M5 12h14"/>',
    chevron:'<path d="m9 18 6-6-6-6"/>',
    arrow:'<path d="m15 18-6-6 6-6"/>',
    more:'<circle cx="5" cy="12" r="1"/><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/>',
    filter:'<path d="M4 5h16M7 12h10M10 19h4"/>',
    download:'<path d="M12 3v12"/><path d="m7 10 5 5 5-5"/><path d="M5 21h14"/>',
    upload:'<path d="M12 21V9"/><path d="m7 14 5-5 5 5"/><path d="M5 3h14"/>',
    qr:'<rect x="3" y="3" width="6" height="6"/><rect x="15" y="3" width="6" height="6"/><rect x="3" y="15" width="6" height="6"/><path d="M15 15h3v3h-3zM18 18h3v3h-3zM18 12h3M12 18h3"/>',
    camera:'<path d="M4 7h3l2-2h6l2 2h3v12H4z"/><circle cx="12" cy="13" r="4"/>',
    mic:'<rect x="9" y="3" width="6" height="11" rx="3"/><path d="M5 11a7 7 0 0 0 14 0M12 18v3"/>',
    wifiOff:'<path d="m3 3 18 18"/><path d="M5 12a10 10 0 0 1 2.2-1.6M9.5 8.2A10 10 0 0 1 19 12M8.5 16a5 5 0 0 1 7 0M12 20h.01"/>',
    refresh:'<path d="M20 11a8 8 0 1 0-2 5"/><path d="M20 4v7h-7"/>',
    check:'<circle cx="12" cy="12" r="9"/><path d="m8 12 2.5 2.5L16 9"/>',
    x:'<circle cx="12" cy="12" r="9"/><path d="m9 9 6 6M15 9l-6 6"/>',
    key:'<circle cx="8" cy="15" r="4"/><path d="m11 12 8-8M15 8l2 2M17 6l2 2"/>',
    server:'<rect x="3" y="4" width="18" height="6" rx="2"/><rect x="3" y="14" width="18" height="6" rx="2"/><path d="M7 7h.01M7 17h.01"/>',
    sliders:'<path d="M4 7h10M18 7h2M4 17h2M10 17h10"/><circle cx="16" cy="7" r="2"/><circle cx="8" cy="17" r="2"/>',
    layers:'<path d="m12 2 9 5-9 5-9-5 9-5Z"/><path d="m3 12 9 5 9-5M3 17l9 5 9-5"/>',
    activity:'<path d="M3 12h4l2-7 4 14 2-7h6"/>',
    lock:'<rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/>',
    phone:'<rect x="7" y="2" width="10" height="20" rx="2"/><path d="M11 18h2"/>',
    eye:'<path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>',
    mail:'<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
    logout:'<path d="M10 17l5-5-5-5M15 12H3M21 19V5a2 2 0 0 0-2-2h-6"/>',
    flag:'<path d="M5 21V4"/><path d="M5 5h11l-2 4 2 4H5"/>',
    palette:'<path d="M12 3a9 9 0 0 0 0 18h2a2 2 0 0 0 0-4h-1a2 2 0 0 1 0-4h2a6 6 0 0 0 0-12Z"/><circle cx="7.5" cy="10" r="1"/><circle cx="10" cy="6.5" r="1"/><circle cx="15" cy="6.5" r="1"/>'
  };
  function ico(name, cls='') { return `<svg class="${cls}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${iconPaths[name] || iconPaths.file}</svg>`; }
  const pill=(t,k='info')=>`<span class="status ${k}">${t}</span>`;
  const tag=(t,k='')=>`<span class="tag ${k}">${t}</span>`;
  const avatar=(name)=>`<span class="cell-avatar">${name.split(/\s+/).slice(0,2).map(x=>x[0]).join('')}</span>`;
  const btn=(label, icon='plus', kind='')=>`<button class="btn ${kind}" data-toast="${label}">${ico(icon)}${label}</button>`;
  const pageHead=(title, desc, actions='')=>`<div class="page-head"><div><h1>${title}</h1><p>${desc}</p></div><div class="page-actions">${actions}</div></div>`;
  const metric=(label,value,delta,icon='activity',tone='')=>`<div class="card metric"><div class="metric-icon ${tone}">${ico(icon)}</div><div class="label">${label}</div><div class="value">${value}</div><div class="delta ${delta?.startsWith('+')?'up':delta?.startsWith('-')?'down':''}">${delta||'&nbsp;'}</div></div>`;
  const notice=(html,tone='')=>`<div class="notice ${tone}">${ico(tone==='danger'?'alert':tone==='success'?'check':'activity')}<div>${html}</div></div>`;
  const mini=(icon,title,sub,tone='')=>`<div class="list-row"><div class="mini-icon ${tone}">${ico(icon)}</div><div class="grow"><strong>${title}</strong><small>${sub}</small></div>${ico('chevron')}</div>`;
  const row=(name,sub,cells,open)=>`<tr ${open?`data-open="${open}"`:''}><td><div class="cell-main">${avatar(name)}<div><strong>${name}</strong><small>${sub||''}</small></div></div></td>${cells.map(c=>`<td>${c}</td>`).join('')}</tr>`;
  const searchToolbar=(placeholder,extra='')=>`<div class="toolbar"><div class="search-field">${ico('search')}<input class="input" placeholder="${placeholder}"></div>${extra}${btn('Фильтры','filter')}</div>`;

  const APPS = {
    admin:{label:'Admin / ЧОП', subtitle:'Управление охранной организацией', color:'#3b63f3'},
    mobile:{label:'Mobile / Охранник', subtitle:'Android & iOS', color:'#13a66a'},
    client:{label:'Кабинет заказчика', subtitle:'Контроль исполнения договора', color:'#7a5bea'},
    platform:{label:'Platform', subtitle:'Системный контур ЧОППРО', color:'#1ba6a6'}
  };

  const NAV = {
    admin:[
      ['Главное',[['dashboard','Обзор','dashboard'],['notifications','Уведомления','bell']]],
      ['Персонал',[['employees','Сотрудники','users'],['employee','Карточка сотрудника','user'],['documents','Документы','file'],['users','Пользователи','users'],['roles','Роли и права','shield']]],
      ['Право и договоры',[['licenses','Лицензии','shield'],['contracts','Договоры','file'],['compliance','Compliance','flag']]],
      ['Объекты',[['customers','Заказчики','building'],['customer','Карточка заказчика','building'],['facilities','Объекты','map'],['facility','Карточка объекта','pin'],['posts','Посты','shield'],['post','Карточка поста','shield']]],
      ['Операции',[['schedule','График','calendar'],['shift','Смена','clock'],['assignments','Назначения','users'],['attendance','Attendance review','check'],['patrols','Обходы','route'],['patrol','Карточка обхода','route'],['incidents','Происшествия','alert'],['incident','Карточка происшествия','alert']]],
      ['Аналитика',[['reports','Отчеты','chart'],['audit','Аудит','audit'],['settings','Настройки','settings']]],
      ['Вход',[['login','Login','key'],['mfa','MFA','lock']]]
    ],
    client:[
      ['Главное',[['dashboard','Обзор','dashboard'],['facilities','Мои объекты','map'],['coverage','Покрытие постов','shield']]],
      ['Исполнение',[['facility','Объект','pin'],['incidents','Происшествия','alert'],['incident','Карточка происшествия','alert'],['reports','Отчеты','chart'],['report','Просмотр отчета','file'],['ack','Подтверждение','check']]],
      ['Аккаунт',[['profile','Профиль','user'],['login','Login','key'],['mfa','MFA','lock']]]
    ],
    platform:[
      ['Платформа',[['tenants','Организации','building'],['tenant','Карточка tenant','building'],['plans','Тарифы и лимиты','layers'],['flags','Feature flags','flag'],['health','Health','activity'],['versions','Версии контента','file'],['breakglass','Break-glass audit','shield'],['audit','Системный аудит','audit']]],
      ['Вход',[['login','Login','key']]]
    ],
    mobile:[
      ['Приложение',[['login','OTP login','phone'],['otp','OTP код','key'],['home','Главная / смена','home'],['shift','Детали смены','clock'],['instruction','Инструкция','file'],['checkin','Check-in / out','check'],['checkresult','Результат check-in','check'],['qr','QR сканер','qr'],['patrol','Обход','route'],['incident','Создать происшествие','alert'],['offline','Offline queue','wifiOff'],['notifications','Уведомления','bell'],['profile','Профиль / согласия','user']]]
    ]
  };

  function allScreens(){
    const out=[];
    Object.entries(NAV).forEach(([app,groups])=>groups.forEach(([g,items])=>items.forEach(([id,title,icon])=>out.push({app,id,title,icon,group:g}))));
    return out;
  }

  const screenIndex = Object.fromEntries(allScreens().map(s=>[`${s.app}:${s.id}`,s]));

  function currentRoute(){
    const q=new URLSearchParams(location.search);
    const app=q.get('app')||'map';
    const screen=q.get('screen')||(app==='map'?'map':app==='mobile'?'home':app==='platform'?'tenants':'dashboard');
    return {app,screen};
  }
  function go(app,screen){
    const u=new URL(location.href);u.search='';u.searchParams.set('app',app);u.searchParams.set('screen',screen);history.pushState({},'',u);render();
  }
  window.addEventListener('popstate', () => render());

  function appNav(app,active){
    if(!NAV[app])return '';
    return NAV[app].map(([group,items])=>`<div class="nav-group"><div class="nav-group-title">${group}</div>${items.map(([id,title,icon])=>`<a class="nav-item ${active===id?'active':''}" href="?app=${app}&screen=${id}" data-nav="${app}:${id}">${ico(icon)}<span>${title}</span></a>`).join('')}</div>`).join('');
  }

  function topBar(app){
    const meta=APPS[app]||{label:'UI/UX карта',subtitle:'Все окна ЧОППРО'};
    return `<header class="proto-top"><div class="brand"><div class="brand-mark">Ч</div><div class="brand-copy"><strong>ЧОППРО</strong><span>${meta.subtitle}</span></div></div><div class="top-search" data-command>${ico('search')}<span>Поиск по экранам и модулям</span><kbd>⌘ K</kbd></div><div class="top-actions"><button class="icon-btn" data-toast="Уведомления">${ico('bell')}<span class="dot"></span></button><button class="icon-btn" data-nav="map:map" title="Карта экранов">${ico('layers')}</button><div class="user-chip"><div class="avatar">АС</div><div><b>${app==='client'?'Анна Смирнова':app==='platform'?'Platform Admin':app==='mobile'?'Иван Иванов':'Алексей Сергеев'}</b><span>${meta.label}</span></div></div></div></header>`;
  }

  function shell(app,screen,content){
    const options=Object.entries(APPS).map(([id,m])=>`<option value="${id}" ${id===app?'selected':''}>${m.label}</option>`).join('');
    return `<div class="proto-root"><div class="proto-frame">${topBar(app)}<div class="proto-body"><aside class="sidebar"><div class="switcher"><div class="switcher-label">Контур</div><select class="app-select" data-app-switch>${options}</select></div>${appNav(app,screen)}<div class="sidebar-footer"><div class="sidebar-card"><strong>UI/UX прототип</strong><p>Интерактивные макеты Этапа 1. Все данные демонстрационные.</p></div></div></aside><main class="content"><div class="content-wide">${content}</div></main></div></div></div><div class="floating-tools"><button class="icon-btn" data-nav="map:map" title="Все окна">${ico('layers')}</button><button class="icon-btn" data-toast="Ссылка на текущий экран скопирована">${ico('upload')}</button></div>`;
  }

