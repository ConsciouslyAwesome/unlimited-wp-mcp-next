import { Denied } from './security.js';
import { SKILLS } from './skills.generated.js';

const text=v=>typeof v==='string'?v.slice(0,8000):null;
const https=v=>{try {return new URL(v).protocol==='https:'?v:null;}catch{return null;}};
// Source/license links are metadata only, never fetched; preserve original HTTP links too.
const metadataURL=v=>{try{return ['http:','https:'].includes(new URL(v).protocol)?v:null;}catch{return null;}};
export function loadSkill({name,query,resource}={}) {
  if(resource&&!name)throw new Denied('resource requires an exact skill name.');
  const catalog=SKILLS.map(({name,keywords,source,resources})=>({name,keywords,source,resources:Object.keys(resources)}));
  if(name) {
    const skill=SKILLS.find(s=>s.name===name);
    if(!skill)throw new Denied('Unknown skill. Load the catalog to see supported names.');
    if(resource&&!Object.hasOwn(skill.resources,resource))throw new Denied('Unknown skill resource.');
    return {name,source:skill.source,resource:resource||null,guidance:skill.guidance,resources:Object.keys(skill.resources),authorization:'Guidance grants no authorization. Existing WordPress approval policy applies.'};
  }
  if(!query)return {skills:catalog};
  const words=new Set(query.toLowerCase().match(/[a-z]+/g)||[]);
  const ranked=catalog.map(s=>({...s,score:s.keywords.split(' ').filter(w=>words.has(w)).length})).filter(s=>s.score).sort((a,b)=>b.score-a.score||a.name.localeCompare(b.name));
  if(ranked.length&&(!ranked[1]||ranked[0].score>ranked[1].score))return loadSkill({name:ranked[0].name});
  return {candidates:ranked,skills:ranked.length?undefined:catalog};
}

async function providerJSON(url,headers={}) {
  const response=await fetch(url,{headers,redirect:'manual',signal:AbortSignal.timeout(10000)});
  if(!response.ok)throw new Error('Image provider unavailable.');
  if(Number(response.headers.get('content-length'))>1048576)throw new Error('Image response too large.');
  const reader=response.body.getReader();let size=0;const chunks=[];
  try {while(true){const {value,done}=await reader.read();if(done)break;size+=value.length;if(size>1048576)throw new Error('Image response too large.');chunks.push(value);}}
  finally {await reader.cancel();}
  const bytes=new Uint8Array(size);let offset=0;for(const chunk of chunks){bytes.set(chunk,offset);offset+=chunk.length;}
  const data=JSON.parse(new TextDecoder().decode(bytes));
  if(!Array.isArray(data.results))throw new Error('Invalid image provider response.');
  return data.results;
}
function normalize(image,provider) {
  if(!image||typeof image!=='object')return null;
  if(provider==='Unsplash')return {
    id:text(image.id),title:text(image.alt_description||image.description),url:https(image.urls?.regular),thumbnail:https(image.urls?.thumb),preview_url:https(image.urls?.small),source_url:https(image.links?.html),
    creator:text(image.user?.name),creator_url:https(image.user?.links?.html),attribution:`Photo by ${text(image.user?.name)||'unknown creator'} on Unsplash`,license:'Unsplash License',license_version:null,license_url:'https://unsplash.com/license',download_location:https(image.links?.download_location),provider,width:image.width,height:image.height,
  };
  return {id:text(image.id),title:text(image.title),url:https(image.url),thumbnail:https(image.thumbnail),preview_url:https(image.thumbnail),source_url:metadataURL(image.foreign_landing_url),creator:text(image.creator),creator_url:metadataURL(image.creator_url),attribution:text(image.attribution),license:text(image.license),license_version:text(image.license_version),license_url:metadataURL(image.license_url),provider,width:image.width,height:image.height};
}
export async function searchImages(env,{query,count=10,orientation}) {
  if(typeof query!=='string'||!query.trim()||query.length>200||!Number.isInteger(count)||count<1||count>10||orientation&&!['landscape','portrait','squarish'].includes(orientation))throw new Denied('Invalid image search arguments.');
  const providers=env.UNSPLASH_ACCESS_KEY?['Unsplash','Openverse']:['Openverse'];
  const failures=[];
  for(const provider of providers) {
    try {
      const url=new URL(provider==='Unsplash'?'https://api.unsplash.com/search/photos':'https://api.openverse.org/v1/images/');
      url.searchParams.set(provider==='Unsplash'?'query':'q',query);
      url.searchParams.set(provider==='Unsplash'?'per_page':'page_size',String(count));
      if(orientation)url.searchParams.set(provider==='Unsplash'?'orientation':'aspect_ratio',provider==='Unsplash'?orientation:{landscape:'wide',portrait:'tall',squarish:'square'}[orientation]);
      const headers=provider==='Unsplash'?{Authorization:'Client-ID '+env.UNSPLASH_ACCESS_KEY,'Accept-Version':'v1'}:{};
      const rows=await providerJSON(url,headers);
      const results=rows.map(i=>normalize(i,provider)).filter(i=>i?.url).slice(0,count);
      return {provider,query,count,orientation:orientation||null,results,untrusted_provider_metadata:true,fallback_from:failures,usage_guidance:provider==='Unsplash'?'Hotlink returned image URLs; credit the photographer and Unsplash with links. Trigger the returned download_location through an authorized integration when selecting a photo for download.':'Preserve each result attribution and license; check its source before reuse.'};
    } catch {failures.push(provider);}
  }
  throw new Denied('Image providers unavailable. Retry later or configure an Unlimited-owned UNSPLASH_ACCESS_KEY.');
}
