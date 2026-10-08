'use strict';
const assert=require('node:assert/strict'),crypto=require('node:crypto');
const origin=process.env.PORTAL_ORIGIN||'http://127.0.0.1:8092';
async function request(path,method='GET',body=null,token=null,extra={}){
 const headers={'Accept':'application/json','X-Choppro-Client':'native',...(body?{'Content-Type':'application/json'}:{}),...(token?{'X-Choppro-Authorization':'Bearer '+token}:{}),...extra};
 const response=await fetch(origin+'/demo-api.php?'+new URLSearchParams({space:'mobile',path}),{method,headers,credentials:'omit',...(body?{body:JSON.stringify(body)}:{})});
 if(!headers.Origin)assert.equal(response.headers.has('set-cookie'),false,'Native request does not use browser cookies');
 return {status:response.status,data:await response.json()};
}
(async()=>{
 const first=await request('/v1/auth/otp/request','POST',{phone:'+79000000007'});assert.equal(first.status,200);assert.ok(first.data.demo_code);
 const verified=await request('/v1/auth/otp/verify','POST',{challenge_id:first.data.challenge_id,code:String(first.data.demo_code)});assert.equal(verified.status,200);const old=verified.data;
 const snapshot=await request('/v1/mobile/snapshot','GET',null,old.access_token);assert.equal(snapshot.status,200);assert.equal(snapshot.data.user.id,old.user.id);assert.ok(Array.isArray(snapshot.data.assignments));assert.ok(snapshot.data.policy);
 assert.equal((await request('/v1/mobile/snapshot','GET',null,'invalid')).status,401);
 assert.equal((await request('/v1/mobile/snapshot','GET',null,old.access_token,{Origin:'https://unrelated.example'})).status,401);
 const event={id:crypto.randomUUID(),type:'POLICY_ACK',device_id:'native-transport-check',client_time:new Date().toISOString(),payload:{policy_id:snapshot.data.policy.id}};
 const saved=await request('/v1/mobile/events','POST',event,old.access_token);assert.equal(saved.status,200);assert.equal(saved.data.ok,true);assert.deepEqual((await request('/v1/mobile/events','POST',event,old.access_token)).data,saved.data);
 const refreshed=await request('/v1/auth/refresh','POST',{refresh_token:old.refresh_token},old.access_token);assert.equal(refreshed.status,200);const fresh=refreshed.data;assert.notEqual(fresh.access_token,old.access_token);
 assert.equal((await request('/v1/mobile/snapshot','GET',null,old.access_token)).status,401);assert.equal((await request('/v1/mobile/snapshot','GET',null,fresh.access_token)).status,200);
 assert.equal((await request('/v1/auth/logout','POST',{},fresh.access_token)).status,200);assert.equal((await request('/v1/mobile/snapshot','GET',null,fresh.access_token)).status,401);
 console.log('PASS native OTP without cookies, shared hosting authorization, scoped snapshot, forged token rejection, browser-origin rejection, idempotent event, refresh rotation and logout');
})().catch(error=>{console.error(error.message);process.exit(1);});
