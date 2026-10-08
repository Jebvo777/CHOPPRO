import * as SQLite from 'expo-sqlite';
import * as SecureStore from 'expo-secure-store';
import * as Crypto from 'expo-crypto';
import type { KeyValue } from '../core/types';
export const secret = { get: (key: string) => SecureStore.getItemAsync(key), set: (key: string,value: string) => SecureStore.setItemAsync(key,value,{ keychainAccessible: SecureStore.WHEN_UNLOCKED_THIS_DEVICE_ONLY }), remove: (key: string) => SecureStore.deleteItemAsync(key) };
export async function openStorage(namespace: string): Promise<KeyValue> {
  const hash = await Crypto.digestStringAsync(Crypto.CryptoDigestAlgorithm.SHA256,namespace);
  const keyName = 'db.' + hash;
  let key = await secret.get(keyName);
  if (!key) { key = Array.from(await Crypto.getRandomBytesAsync(32),value => value.toString(16).padStart(2,'0')).join(''); await secret.set(keyName,key); }
  if (!/^[a-f0-9]{64}$/.test(key)) throw new Error('Не удалось открыть локальное хранилище');
  const db = await SQLite.openDatabaseAsync('choppro-' + hash + '.db');
  await db.execAsync("PRAGMA key = \"x'" + key + "'\"; PRAGMA journal_mode = WAL; CREATE TABLE IF NOT EXISTS kv (key TEXT PRIMARY KEY,value TEXT NOT NULL);");
  return { get: async name => (await db.getFirstAsync<{value:string}>('SELECT value FROM kv WHERE key=?',name))?.value ?? null, set: async (name,value) => { await db.runAsync('INSERT INTO kv(key,value) VALUES(?,?) ON CONFLICT(key) DO UPDATE SET value=excluded.value',name,value); }, remove: async name => { await db.runAsync('DELETE FROM kv WHERE key=?',name); } };
}
