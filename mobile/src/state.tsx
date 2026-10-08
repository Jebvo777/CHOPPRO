import React, { createContext, useContext, useEffect, useRef, useState } from 'react';
import { AppState, Platform } from 'react-native';
import Constants from 'expo-constants';
import * as Crypto from 'expo-crypto';
import * as Network from 'expo-network';
import { Api, normalizeEndpoint } from './core/api';
import { EventQueue } from './core/queue';
import type { EventType, KeyValue, QueuedEvent, Row, Session, Snapshot } from './core/types';
import { openStorage, secret } from './platform/storage';
import { appendFile, removeFile } from './platform/files';
interface State {
  ready: boolean; endpoint: string; api: Api | null; snapshot: Snapshot | null; events: QueuedEvent[];
  online: boolean; syncing: boolean; message: string; device: string;
  configure(value: string): Promise<Api>; login(session: Session): Promise<void>; logout(): Promise<void>;
  refresh(): Promise<void>; sync(): Promise<void>; retry(id: string): Promise<void>;
  enqueue(type: EventType,payload: Row): Promise<string>;
  incident(payload:Row,files:Row[]):Promise<string>;
  draft(key:string,value?:Row|null):Promise<Row|null>;
}
const Context = createContext<State | null>(null);
export function useApp(): State { const value = useContext(Context); if (!value) throw new Error('Приложение не готово'); return value; }
export function Provider({children}: {children: React.ReactNode}) {
  const [ready,setReady] = useState(false), [endpoint,setEndpoint] = useState(''), [api,setApi] = useState<Api | null>(null), [snapshot,setSnapshot] = useState<Snapshot | null>(null), [events,setEvents] = useState<QueuedEvent[]>([]), [online,setOnline] = useState(true), [syncing,setSyncing] = useState(false), [message,setMessage] = useState(''), [device,setDevice] = useState('');
  const current = useRef<Api | null>(null), storage = useRef<KeyValue | null>(null), queue = useRef<EventQueue | null>(null), flight = useRef<Promise<void> | null>(null), onlineRef = useRef(true);
  async function connect(session: Session,a: Api) {
    await a.use(session); const owner = a.endpoint + '|' + session.user.id;
    storage.current = await openStorage(owner);
    const cached = await storage.current.get('snapshot');
    setSnapshot(cached ? JSON.parse(cached) as Snapshot : null);
    queue.current = new EventQueue(storage.current, async event => {
      if (current.current !== a || !a.session) throw new Error('Войдите в тот же аккаунт для отправки событий');
      if (event.type === 'UPLOAD') {
        const form = new FormData(); form.append('event_key',event.id); form.append('incident_event_key',event.payload.incident_event_key);
        const cleanup = await appendFile(form,event.payload.file);
        try { const result = await a.request('/v1/mobile/upload','POST',form); await removeFile(event.payload.file.uri ?? ''); return result; }
        finally { if (typeof cleanup === 'function') await cleanup(); }
      }
      const payload = { ...event.payload }; delete payload.depends_on;
      return a.request('/v1/mobile/events','POST',{id:event.id,type:event.type,client_time:event.client_time,device_id:event.device_id,payload});
    },() => setEvents(queue.current?.list() ?? []));
    await queue.current.load(); setEvents(queue.current.list());
    try { await refresh(); await sync(); } catch (error) { setMessage((error as Error).message); }
  }
  async function refresh() {
    const a = current.current; if (!a?.session) return;
    const data = await a.request('/v1/mobile/snapshot') as Snapshot;
    if (current.current !== a) return;
    setSnapshot(data); await storage.current?.set('snapshot',JSON.stringify(data)); setMessage(''); setOnline(true); onlineRef.current = true;
  }
  async function sync() {
    if (flight.current) return flight.current;
    flight.current = (async () => {
      if (!current.current?.session || !queue.current) return;
      setSyncing(true);
      try { await queue.current.flush(); await refresh(); }
      catch (error) { setMessage((error as Error).message); if (!((error as Row).status)) { setOnline(false); onlineRef.current = false; } }
      finally { setSyncing(false); }
    })().finally(() => { flight.current = null; });
    return flight.current;
  }
  async function configure(value: string) {
    const result = normalizeEndpoint(value,__DEV__ || (Platform.OS === 'web' && /localhost|127\.0\.0\.1/.test(value)));
    await secret.set('endpoint',result); setEndpoint(result);
    const a = new Api(result,Platform.OS !== 'web',async session => { if (session) await secret.set('session',JSON.stringify(session)); else await secret.remove('session'); });
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
  async function draft(key:string,value?:Row|null):Promise<Row|null> {
    if(!storage.current)return null;
    if(value!==undefined){await storage.current.set('draft:'+key,JSON.stringify(value));return value;}
    const saved=await storage.current.get('draft:'+key);return saved?JSON.parse(saved) as Row:null;
  }
  async function retry(id: string) { await queue.current?.retry(id); await sync(); }
  useEffect(() => {
    let alive = true;
    void (async () => {
      let savedDevice = await secret.get('device'); if (!savedDevice) { savedDevice = Crypto.randomUUID(); await secret.set('device',savedDevice); } setDevice(savedDevice);
      const url = await secret.get('endpoint') || String(Constants.expoConfig?.extra?.apiUrl ?? '') || (Platform.OS === 'web' ? location.origin + location.pathname.replace(/\/demo\.php.*$/,'').replace(/\/mobile.*$/,'') : '');
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
  return <Context.Provider value={{ready,endpoint,api,snapshot,events,online,syncing,message,device,configure,login,logout,refresh,sync,retry,enqueue,incident,draft}}>{children}</Context.Provider>;
}
