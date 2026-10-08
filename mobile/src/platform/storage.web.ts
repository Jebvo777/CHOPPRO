import type { KeyValue } from '../core/types';
export const secret = { get: async (key: string) => sessionStorage.getItem('choppro.' + key), set: async (key: string,value: string) => { sessionStorage.setItem('choppro.' + key,value); }, remove: async (key: string) => { sessionStorage.removeItem('choppro.' + key); } };
export async function openStorage(namespace: string): Promise<KeyValue> {
  const raw = await crypto.subtle.digest('SHA-256',new TextEncoder().encode(namespace));
  const hash = Array.from(new Uint8Array(raw),value => value.toString(16).padStart(2,'0')).join('');
  const name = 'choppro-' + hash;
  let encoded = await secret.get('key.' + hash);
  if (!encoded) { encoded = btoa(String.fromCharCode(...crypto.getRandomValues(new Uint8Array(32)))); await secret.set('key.' + hash,encoded); }
  const key = await crypto.subtle.importKey('raw',Uint8Array.from(atob(encoded),x => x.charCodeAt(0)),{name:'AES-GCM'},false,['encrypt','decrypt']);
  const db = await new Promise<IDBDatabase>((resolve,reject) => { const req = indexedDB.open(name,1); req.onupgradeneeded = () => req.result.createObjectStore('kv'); req.onsuccess = () => resolve(req.result); req.onerror = () => reject(req.error); });
  async function transaction(mode: IDBTransactionMode,operation: (store: IDBObjectStore) => IDBRequest): Promise<any> { return new Promise((resolve,reject) => { const tx = db.transaction('kv',mode); const request = operation(tx.objectStore('kv')); let result: any; request.onsuccess = () => { result = request.result; }; tx.oncomplete = () => resolve(result); tx.onerror = () => reject(tx.error); tx.onabort = () => reject(tx.error); }); }
  return {
    get: async id => { const record = await transaction('readonly',store => store.get(id)); if (!record) return null; try { return new TextDecoder().decode(await crypto.subtle.decrypt({name:'AES-GCM',iv:record.iv},key,record.bytes)); } catch { await transaction('readwrite',store => store.delete(id)); return null; } },
    set: async (id,value) => { const iv = crypto.getRandomValues(new Uint8Array(12)); const bytes = await crypto.subtle.encrypt({name:'AES-GCM',iv},key,new TextEncoder().encode(value)); await transaction('readwrite',store => store.put({iv,bytes},id)); },
    remove: async id => { await transaction('readwrite',store => store.delete(id)); }
  };
}
