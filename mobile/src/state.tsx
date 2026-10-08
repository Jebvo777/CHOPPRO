import React, { createContext, useContext, useEffect, useRef, useState } from 'react';
import { AppState, Platform } from 'react-native';
import Constants from 'expo-constants';
import * as Crypto from 'expo-crypto';
import * as Network from 'expo-network';
import { Api, normalizeEndpoint } from './core/api';
import { EventQueue } from './core/queue';
import type { EventType, KeyValue, QueuedEvent, Row, Session, Snapshot } from './core/types';
import { openStorage, secret } from './platform/storage';
import { appendFile, removeFile, downloadFile } from './platform/files';
interface State {
  ready: boolean; endpoint: string; api: Api | null; snapshot: Snapshot | null; events: QueuedEvent[];
  online: boolean; syncing: boolean; message: string; device: string;
  configure(value: string): Promise<Api>; login(session: Session): Promise<void>; logout(): Promise<void>;
  refresh(): Promise<void>; sync(): Promise<void>; retry(id: string): Promise<void>;
  enqueue(type: EventType,payload: Row): Promise<string>;
  incident(payload:Row,files:Row[]):Promise<string>;
  draft(key:string,value?:Row|null):Promise<Row|null>;
  stash(file:Row):Promise<Row>; discard(file:Row):Promise<void>;
  openFile(file:Row):Promise<void>;
}
const Context = createContext<State | null>(null);
export function useApp(): State { const value = useContext(Context); if (!value) throw new Error('Приложение не готово'); return value; }
export function Provider({children}: {children: React.ReactNode}) {
  const [ready,setReady] = useState(false), [endpoint,setEndpoint] = useState(''), [api,setApi] = useState<Api | null>(null), [snapshot,setSnapshot] = useState<Snapshot | null>(null), [events,setEvents] = useState<QueuedEvent[]>([]), [online,setOnline] = useState(true), [syncing,setSyncing] = useState(false), [message,setMessage] = useState(''), [device,setDevice] = useState('');
  const current = useRef<Api | null>(null), storage = useRef<KeyValue | null>(null), queue = useRef<EventQueue | null>(null), flight = useRef<Promise<void> | null>(null), syncAgain = useRef(false), onlineRef = useRef(true);
  async function connect(session: Session,a: Api) {
    await a.use(session); const owner = a.endpoint + '|' + session.user.id;
    const ownerStore = await openStorage(owner); storage.current = ownerStore;
    const cached = await ownerStore.get('snapshot');
    setSnapshot(cached ? JSON.parse(cached) as Snapshot : null);
    const ownerQueue = new EventQueue(ownerStore, async event => {
      if (current.current !== a || !a.session) throw new Error('Войдите в тот же аккаунт для отправки событий');
      if (event.type === 'UPLOAD') {
        const raw=event.payload.file.fileKey?await ownerStore.get(event.payload.file.fileKey):null;
        const file=raw?JSON.parse(raw) as Row:event.payload.file;
        if(!file.bytes)throw Object.assign(new Error('Вложение не найдено на устройстве'),{status:422});
        const form = new FormData(); form.append('event_key',event.id); form.append('incident_event_key',event.payload.incident_event_key);
        const cleanup = await appendFile(form,file);
        try { const result = await a.request('/v1/mobile/upload','POST',form); await removeFile(file.uri ?? '');if(event.payload.file.fileKey)await ownerStore.remove(event.payload.file.fileKey);return result; }
        finally { if (typeof cleanup === 'function') await cleanup(); }
      }
      const payload = { ...event.payload }; delete payload.depends_on;
      return a.request('/v1/mobile/events','POST',{id:event.id,type:event.type,client_time:event.client_time,device_id:event.device_id,payload});
    },() => {if(queue.current===ownerQueue)setEvents(ownerQueue.list());});
    queue.current=ownerQueue;await ownerQueue.load(); setEvents(ownerQueue.list());
    if(cached)setReady(true);void sync();
  }
  async function refresh() {
    const a = current.current; if (!a?.session) return;
    const data = await a.request('/v1/mobile/snapshot') as Snapshot;
    if (current.current !== a) return;
    setSnapshot(data); await storage.current?.set('snapshot',JSON.stringify(data)); setMessage(''); setOnline(true); onlineRef.current = true;
  }
  async function sync() {
    if (flight.current) { syncAgain.current=true;return flight.current; }
    flight.current = (async () => {
      const a=current.current,q=queue.current;if (!a?.session || !q) return;
      setSyncing(true);
      try { do { syncAgain.current=false;await q.flush();if(current.current===a)await refresh(); } while(syncAgain.current&&current.current===a&&queue.current===q&&onlineRef.current); }
      catch (error) { if(current.current===a){setMessage((error as Error).message); if (!((error as Row).status)) { setOnline(false); onlineRef.current = false; }} }
      finally { if(current.current===a)setSyncing(false); }
    })().finally(() => { flight.current = null; });
    return flight.current;
  }
  async function configure(value: string) {
    const result = normalizeEndpoint(value,__DEV__ || (Platform.OS === 'web' && /localhost|127\.0\.0\.1/.test(value)));
    await secret.set('endpoint',result); setEndpoint(result);
    const a = new Api(result,Platform.OS !== 'web',async session => { if(current.current!==a)return;if (session) await secret.set('session',JSON.stringify(session)); else await secret.remove('session'); });
    current.current = a; setApi(a); return a;
  }
  async function login(session: Session) { if (!current.current) throw new Error('Укажите адрес сервера'); await connect(session,current.current); }
  async function logout() {
    const a = current.current;
    if (a?.session) {
      try { await a.request('/v1/auth/logout','POST',{}); }
      catch { await secret.set('revocation',JSON.stringify({endpoint:a.endpoint,refresh_token:a.session.refresh_token})); }
      a.session = null;
    }
    await secret.remove('session'); queue.current = null; storage.current = null; setSnapshot(null); setEvents([]); setMessage('');
  }
  async function enqueue(type: EventType,payload: Row): Promise<string> {
    if (!queue.current || !current.current?.session) throw new Error('Сначала войдите в приложение');
    const id = Crypto.randomUUID();
    await queue.current.add({id,type,client_time:new Date().toISOString(),device_id:device,payload:{...payload,offline:!onlineRef.current},state:'PENDING',attempts:0,nextAttempt:0});
    void sync(); return id;
  }
  async function incident(payload:Row,files:Row[]):Promise<string> {
    if(!queue.current || !current.current?.session)throw new Error('Сначала войдите в приложение');
    const id=Crypto.randomUUID(),time=new Date().toISOString();
    const items:QueuedEvent[]=[{id,type:'INCIDENT_CREATE',client_time:time,device_id:device,payload:{...payload,offline:!onlineRef.current},state:'PENDING',attempts:0,nextAttempt:0},...files.map(file=>({id:Crypto.randomUUID(),type:'UPLOAD' as EventType,client_time:time,device_id:device,payload:{incident_event_key:id,depends_on:id,file},state:'PENDING' as const,attempts:0,nextAttempt:0}))];
    await queue.current.addBatch(items);void sync();return id;
  }
  async function stash(file:Row):Promise<Row> {if(!storage.current)throw new Error('Приложение не подключено');const fileKey='file:'+Crypto.randomUUID();await storage.current.set(fileKey,JSON.stringify(file));return {fileKey,name:file.name,mime:file.mime,size:file.size};}
  async function discard(file:Row):Promise<void> {if(file.fileKey)await storage.current?.remove(file.fileKey);}
  async function openFile(file:Row):Promise<void> {
    const a=current.current;if(!a?.session)throw new Error('Сначала войдите в приложение');
    const link=await a.request('/v1/files/'+file.id+'/link');const params=new URLSearchParams({space:'mobile',path:link.path,...link.query});
    await downloadFile(a.endpoint+'?'+params.toString(),a.session.access_token,file.name,file.mime);
  }
  async function draft(key:string,value?:Row|null):Promise<Row|null> {
    if(!storage.current)return null;
    if(value!==undefined){await storage.current.set('draft:'+key,JSON.stringify(value));return value;}
    const saved=await storage.current.get('draft:'+key);return saved?JSON.parse(saved) as Row:null;
  }
  async function retry(id: string) { await queue.current?.retry(id); await sync(); }
  useEffect(() => {
    let alive = true;
    void (async () => {
      try {const network=await Network.getNetworkStateAsync();onlineRef.current=network.isConnected!==false;setOnline(onlineRef.current);}catch {}
      let savedDevice = await secret.get('device'); if (!savedDevice) { savedDevice = Crypto.randomUUID(); await secret.set('device',savedDevice); } setDevice(savedDevice);
      const url = await secret.get('endpoint') || (Platform.OS === 'web' ? location.origin + location.pathname.replace(/\/demo\.php.*$/,'').replace(/\/mobile.*$/,'') : String(Constants.expoConfig?.extra?.apiUrl ?? ''));
      if (url) {
        await configure(url);
        const revoke = await secret.get('revocation');
        if (revoke) try { const value = JSON.parse(revoke); const previous = new Api(value.endpoint,true,async () => {}); const session = await previous.request('/v1/auth/refresh','POST',{refresh_token:value.refresh_token}) as Session; previous.session = session; await previous.request('/v1/auth/logout','POST',{}); await secret.remove('revocation'); } catch {}
        const raw = await secret.get('session'); if (raw && current.current) await connect(JSON.parse(raw) as Session,current.current);
      }
      if (alive) setReady(true);
    })().catch(error => { setMessage(error.message); setReady(true); });
    const timer = setInterval(() => { void Network.getNetworkStateAsync().then(state => { onlineRef.current = state.isConnected !== false; setOnline(onlineRef.current); if (onlineRef.current) void sync(); }); },15000);
    const subscription = AppState.addEventListener('change',state => { if (state === 'active') void sync(); });
    return () => { alive = false; clearInterval(timer); subscription.remove(); };
  },[]);
  return <Context.Provider value={{ready,endpoint,api,snapshot,events,online,syncing,message,device,configure,login,logout,refresh,sync,retry,enqueue,incident,draft,stash,discard,openFile}}>{children}</Context.Provider>;
}
