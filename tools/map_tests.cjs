'use strict';
const assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm'),path=require('node:path');
const context={URL};vm.runInNewContext(fs.readFileSync(path.join(__dirname,'../web/shared/map.js'),'utf8')+';globalThis.PointMap=YandexPointMap;',context);const Map=context.PointMap,map=Object.create(Map.prototype);let checks=0;
for(const zoom of [3,10,16,19]){map.zoom=zoom;for(const [lng,lat] of [[45.018316,53.195878],[37.6173,55.7558],[-0.1276,51.5072],[151.2093,-33.8688],[179.9,80],[-179.9,-80],[0,0]]){const result=map.inverse(...map.project(lng,lat));assert(Math.abs(result[0]-lng)<1e-7&&Math.abs(result[1]-lat)<1e-7,'Map coordinates preserve the selected place');checks++;}}
assert.equal(Map.fromLink('https://yandex.ru/maps/?ll=45.018316,53.195878').lat,53.195878);assert.equal(Map.fromLink('https://example.org/?ll=45,53'),null);assert.equal(Map.fromLink('https://yandex.ru/maps/?ll=200,53'),null);checks+=3;
console.log('PASS '+checks+' map coordinate checks');
