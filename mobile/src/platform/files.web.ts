import type { Row } from '../core/types';
export async function preserveFile(uri: string,name: string,mime: string): Promise<Row> {
  const blob = await (await fetch(uri)).blob();
  if (blob.size > 10485760) throw new Error('Вложение должно быть не больше 10 МБ');
  const data = await new Promise<string>((resolve,reject) => { const reader = new FileReader(); reader.onload = () => resolve(String(reader.result)); reader.onerror = reject; reader.readAsDataURL(blob); });
  return {uri:data,name,mime};
}
export async function appendFile(form: FormData,file: Row): Promise<void> { form.append('file',await (await fetch(file.uri)).blob(),file.name); }
export async function removeFile(_uri: string): Promise<void> {}
