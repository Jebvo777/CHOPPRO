import * as FS from 'expo-file-system/legacy';
import * as Crypto from 'expo-crypto';
import * as Sharing from 'expo-sharing';
import type { Row } from '../core/types';
export async function preserveFile(uri: string,name: string,mime: string,maxMb=20): Promise<Row> {
  const info = await FS.getInfoAsync(uri);
  if (!info.exists || ('size' in info && info.size > maxMb*1048576)) throw new Error('Вложение должно быть не больше '+maxMb+' МБ');
  const bytes = await FS.readAsStringAsync(uri,{encoding:FS.EncodingType.Base64});
  if (uri.startsWith(FS.cacheDirectory ?? '!')) await FS.deleteAsync(uri,{idempotent:true});
  return {bytes,name,mime,size:'size' in info?info.size:0};
}
export async function appendFile(form: FormData,file: Row): Promise<()=>Promise<void>> {
  const path = FS.cacheDirectory + 'upload-' + Crypto.randomUUID() + '.' + (String(file.name).split('.').pop() ?? 'bin').replace(/[^a-z0-9]/gi,'');
  await FS.writeAsStringAsync(path,file.bytes,{encoding:FS.EncodingType.Base64});
  form.append('file',{uri:path,name:file.name,type:file.mime} as unknown as Blob);
  return () => FS.deleteAsync(path,{idempotent:true});
}
export async function removeFile(_uri: string): Promise<void> {}
export async function downloadFile(url:string,token:string,name:string,mime:string):Promise<void>{
  const path=FS.cacheDirectory+'view-'+Crypto.randomUUID()+'.'+(name.split('.').pop()??'bin').replace(/[^a-z0-9]/gi,'');
  try{const result=await FS.downloadAsync(url,path,{headers:{Authorization:'Bearer '+token,'X-Choppro-Client':'native'}});if(result.status!==200)throw new Error('Файл недоступен. Обновите данные и повторите.');if(!await Sharing.isAvailableAsync())throw new Error('На устройстве недоступен просмотр файла');await Sharing.shareAsync(path,{mimeType:mime,dialogTitle:name});}finally{await FS.deleteAsync(path,{idempotent:true});}
}
