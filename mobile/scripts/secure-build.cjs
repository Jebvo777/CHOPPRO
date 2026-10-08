'use strict';
const fs=require('node:fs'),path=require('node:path');
const root=path.resolve(__dirname,'..');
function alter(file,change){const s=fs.readFileSync(file,'utf8');if(s.includes('CHOPPRO_BUILD_GUARD'))return;fs.writeFileSync(file,change(s));}
const braces=path.join(path.dirname(require.resolve('braces/package.json',{paths:[root]})),'index.js');
alter(braces,s=>s+`
// CHOPPRO_BUILD_GUARD
const chopproBracesOriginal=module.exports;
function chopproPattern(value){
 if(typeof value==='string'){if(value.length>65536)throw new RangeError('Pattern length');let depth=0;for(let n=0;n<value.length;n++){if(value[n]==='\\\\'){n++;continue;}if(value[n]==='{'){if(++depth>64)throw new RangeError('Pattern depth');}else if(value[n]==='}')depth=Math.max(0,depth-1);}}
 else if(Array.isArray(value))value.forEach(chopproPattern);
 else if(value&&Array.isArray(value.nodes)){const stack=[[value,0]];let count=0;while(stack.length){const [node,depth]=stack.pop();if(depth>64||++count>65536)throw new RangeError('AST depth');if(node&&Array.isArray(node.nodes))for(const next of node.nodes)stack.push([next,depth+1]);}}
}
const chopproBraces=function(value,...args){chopproPattern(value);return chopproBracesOriginal(value,...args);};
Object.assign(chopproBraces,chopproBracesOriginal);
for(const name of ['parse','compile','expand','create'])if(typeof chopproBracesOriginal[name]==='function')chopproBraces[name]=function(value,...args){chopproPattern(value);return chopproBracesOriginal[name](value,...args);};
module.exports=chopproBraces;
`);
const rsa=path.join(path.dirname(require.resolve('node-forge/package.json',{paths:[root]})),'lib/rsa.js');
alter(rsa,s=>{const needle='obj.value.length !== 2';if(!s.includes(needle))throw new Error('Inspect current RSA validation before changing dependencies');return s.replace(needle,needle+` || !Array.isArray(obj.value[0].value) || ![1,2].includes(obj.value[0].value.length) || (obj.value[0].value.length === 2 && obj.value[0].value[1].type !== asn1.Type.NULL) /* CHOPPRO_BUILD_GUARD */`);});
