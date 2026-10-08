import type { Row } from '../core/types';
export async function preserveFile(uri: string,name: string,mime: string,maxMb=20): Promise<Row> {
  const blob = await (await fetch(uri)).blob();
  if (blob.size > maxMb*1048576) throw new Error('Вложение должно быть не больше '+maxMb+' МБ');
  const data = await new Promise<string>((resolve,reject) => { const reader = new FileReader(); reader.onload = () => resolve(String(reader.result)); reader.onerror = reject; reader.readAsDataURL(blob); });
  return {bytes:data.slice(data.indexOf(',')+1),name,mime,size:blob.size};
}
export async function appendFile(form: FormData,file: Row): Promise<void> {const raw=atob(file.bytes);const bytes=new Uint8Array(raw.length);for(let i=0;i<raw.length;i++)bytes[i]=raw.charCodeAt(i);form.append('file',new Blob([bytes],{type:file.mime}),file.name);}
export async function removeFile(_uri: string): Promise<void> {}
export async function downloadFile(url:string,token:string,name:string,_mime:string):Promise<void>{const response=await fetch(url,{credentials:'include',headers:{Authorization:'Bearer '+token}});if(!response.ok)throw new Error('Файл недоступен. Обновите данные и повторите.');const path=URL.createObjectURL(await response.blob());const a=document.createElement('a');a.href=path;a.download=name;document.body.append(a);a.click();a.remove();setTimeout(()=>URL.revokeObjectURL(path),1000);}
