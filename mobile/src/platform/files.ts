import * as FS from 'expo-file-system/legacy';
import * as Crypto from 'expo-crypto';
import type { Row } from '../core/types';
export async function preserveFile(uri: string,name: string,mime: string): Promise<Row> {
  const info = await FS.getInfoAsync(uri);
  if (!info.exists || ('size' in info && info.size > 10485760)) throw new Error('Вложение должно быть не больше 10 МБ');
  const bytes = await FS.readAsStringAsync(uri,{encoding:FS.EncodingType.Base64});
  if (uri.startsWith(FS.cacheDirectory ?? '!')) await FS.deleteAsync(uri,{idempotent:true});
  return {bytes,name,mime};
}
export async function appendFile(form: FormData,file: Row): Promise<()=>Promise<void>> {
  const path = FS.cacheDirectory + 'upload-' + Crypto.randomUUID() + '.' + (String(file.name).split('.').pop() ?? 'bin').replace(/[^a-z0-9]/gi,'');
  await FS.writeAsStringAsync(path,file.bytes,{encoding:FS.EncodingType.Base64});
  form.append('file',{uri:path,name:file.name,type:file.mime} as unknown as Blob);
  return () => FS.deleteAsync(path,{idempotent:true});
}
export async function removeFile(_uri: string): Promise<void> {}
