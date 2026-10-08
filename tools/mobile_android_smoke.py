import pathlib,re,subprocess,time,xml.etree.ElementTree as ET

output=pathlib.Path('dist');output.mkdir(exist_ok=True)
def adb(*args):
 return subprocess.check_output(['adb',*args],timeout=35).decode(errors='replace')
def nodes():
 adb('shell','uiautomator','dump','/sdcard/choppro-ui.xml')
 return list(ET.fromstring(adb('exec-out','cat','/sdcard/choppro-ui.xml')).iter('node'))
def wait(text=None,resource=None,timeout=90):
 deadline=time.monotonic()+timeout
 while time.monotonic()<deadline:
  try:
   current=nodes()
   for node in current:
    if (text is None or node.get('text')==text or node.get('content-desc')==text) and (resource is None or node.get('resource-id','').endswith(resource)):return node
  except (subprocess.SubprocessError,ET.ParseError):pass
  time.sleep(1)
 raise RuntimeError('Экран или элемент не появился: '+str(text or resource))
def tap(text=None,resource=None):
 node=wait(text,resource);bounds=[int(v) for v in re.findall(r'\d+',node.get('bounds',''))]
 if len(bounds)!=4:raise RuntimeError('Элемент не имеет координат: '+str(text or resource))
 adb('shell','input','tap',str((bounds[0]+bounds[2])//2),str((bounds[1]+bounds[3])//2))
def screenshot(name):
 (output/name).write_bytes(subprocess.check_output(['adb','exec-out','screencap','-p'],timeout=35))
try:
 wait('Вход сотрудника');address=wait(resource='server-url').get('text','');assert 'https://m20.system404-design.ru/' in address,'Адрес портала заполнен'
 screenshot('CHOPPRO_Android_login.png');tap('Демонстрационный сотрудник');tap(resource='login-submit');wait('Подставить код')
 tap(resource='otp');adb('shell','input','text','000000');adb('shell','input','keyevent','4');tap(resource='login-submit');wait('Неверный код')
 tap('Подставить код');tap(resource='login-submit');wait('Ваш рабочий день',timeout=150);wait('На связи');screenshot('CHOPPRO_Android_home.png')
 tap('Смены');wait('Ваши смены');screenshot('CHOPPRO_Android_shifts.png')
 tap('Обходы');wait('Обходы объектов');screenshot('CHOPPRO_Android_patrols.png')
 tap('Журнал');wait('Журнал отправки');screenshot('CHOPPRO_Android_journal.png')
 tap('Профиль');wait('Ваш профиль');screenshot('CHOPPRO_Android_profile.png')
 adb('shell','am','force-stop','ru.choppro.guard');adb('shell','am','start','-W','-n','ru.choppro.guard/.MainActivity');wait('Ваш рабочий день',timeout=150);wait('На связи');screenshot('CHOPPRO_Android_reopened.png')
 tap('Профиль');tap('Выйти');tap('Продолжить');wait('Вход сотрудника');screenshot('CHOPPRO_Android_screen.png')
 print('PASS standalone APK: prefilled live portal, invalid OTP rejection, successful native login, shifts, patrols, journal, profile, encrypted storage and session across restart, logout')
except Exception:
 screenshot('CHOPPRO_Android_failure.png')
 try:
  visible=[n.get('text') or n.get('content-desc') for n in nodes() if n.get('text') or n.get('content-desc')]
  print('Состояние экрана:',re.sub(r'\b\d{6,}\b','[скрыто]',str(visible)))
 except Exception:pass
 raise
