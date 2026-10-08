import test from 'node:test';
import assert from 'node:assert/strict';
import { Api, ApiError } from '../src/core/api';
import type { Session } from '../src/core/types';
const session: Session = {access_token:'test-access',refresh_token:'test-refresh',expires_in:900,user:{id:'guard'},csrf:'csrf'};
test('Native authentication survives a proxy that removes Authorization',async()=>{
  const original=globalThis.fetch;
  const api=new Api('https://m20.system404-design.ru/demo-api.php',true,async()=>{});await api.use(session);
  globalThis.fetch=async(url,init)=>{const headers=init!.headers as Record<string,string>;assert.equal(headers.Authorization,'Bearer test-access');assert.equal(headers['X-Choppro-Authorization'],headers.Authorization);assert.equal(headers['X-Choppro-Client'],'native');assert.equal(init!.credentials,'omit');assert.equal(new URL(String(url)).searchParams.get('path'),'/v1/mobile/snapshot');return Response.json({assignments:[]});};
  try{assert.deepEqual(await api.request('/v1/mobile/snapshot'),{assignments:[]});}finally{globalThis.fetch=original;}
});
test('Browser requests use their normal authentication transport',async()=>{
  const original=globalThis.fetch;const api=new Api('https://example.ru/demo-api.php',false,async()=>{});await api.use(session);
  globalThis.fetch=async(_url,init)=>{assert.equal((init!.headers as Record<string,string>)['X-Choppro-Authorization'],undefined);assert.equal(init!.credentials,'include');return Response.json({ok:true});};
  try{await api.request('/v1/mobile/snapshot');}finally{globalThis.fetch=original;}
});
test('A renewed session is used in both native headers when replaying a request',async()=>{
  const original=globalThis.fetch;let calls=0;const saved:Session[]=[];const api=new Api('https://example.ru/demo-api.php',true,async value=>{if(value)saved.push(value);});await api.use(session);
  globalThis.fetch=async(url,init)=>{calls++;const path=new URL(String(url)).searchParams.get('path');if(calls===1)return Response.json({code:'SESSION_EXPIRED',message:'Сессия истекла'},{status:401});if(path==='/v1/auth/refresh'){assert.equal(JSON.parse(String(init!.body)).refresh_token,'test-refresh');return Response.json({...session,access_token:'renewed',refresh_token:'rotated'});}assert.equal((init!.headers as Record<string,string>)['X-Choppro-Authorization'],'Bearer renewed');assert.equal((init!.headers as Record<string,string>).Authorization,'Bearer renewed');return Response.json({ok:true});};
  try{assert.deepEqual(await api.request('/v1/mobile/snapshot'),{ok:true});assert.equal(calls,3);assert.equal(saved.at(-1)?.refresh_token,'rotated');}finally{globalThis.fetch=original;}
});
test('An HTML response cannot be treated as a successful login',async()=>{
  const original=globalThis.fetch;const api=new Api('https://example.ru/demo-api.php',true,async()=>{});globalThis.fetch=async()=>new Response('<h1>Ошибка</h1>',{status:200});
  try{await assert.rejects(api.request('/v1/auth/otp/request','POST',{phone:'+79000000007'}),error=>error instanceof ApiError&&error.code==='INVALID_RESPONSE');await assert.rejects(api.use({} as Session),error=>error instanceof ApiError&&error.code==='INVALID_SESSION');assert.equal(api.session,null);}finally{globalThis.fetch=original;}
});
test('A failed connection does not falsely claim that a login was saved',async()=>{
  const original=globalThis.fetch;const api=new Api('https://example.ru/demo-api.php',true,async()=>{});globalThis.fetch=async()=>{throw new Error('offline');};
  try{await assert.rejects(api.request('/v1/auth/otp/request','POST',{}),error=>error instanceof ApiError&&error.code==='NETWORK'&&!error.message.includes('сохранена'));}finally{globalThis.fetch=original;}
});
