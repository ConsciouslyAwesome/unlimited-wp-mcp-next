import assert from 'node:assert/strict';
import {test,afterEach} from 'node:test';
import {readFileSync} from 'node:fs';
import {compatibilityCatalog,translate} from '../src/compatibility.js';
import {searchImages,loadSkill} from '../src/resource-compatibility.js';
import {SKILLS} from '../src/skills.generated.js';
const originalFetch=globalThis.fetch;
afterEach(()=>{globalThis.fetch=originalFetch;});
const row={id:'one',title:'Forest',url:'https://images.example.com/one.jpg',thumbnail:'https://images.example.com/thumb.jpg',foreign_landing_url:'https://source.example.com/one',creator:'A Creator',creator_url:'https://source.example.com/creator',attribution:'Forest by A Creator / CC BY 4.0',license:'by',license_version:'4.0',license_url:'https://creativecommons.org/licenses/by/4.0/',width:1200,height:800};
const response=rows=>new Response(JSON.stringify({results:rows}));
test('image schema exposes only requested arguments and existing site target',()=>{
 const s=compatibilityCatalog().find(t=>t.name==='search_images').inputSchema;
 assert.deepEqual(Object.keys(s.properties),['query','count','orientation','site_url']);
 assert.deepEqual(s.required,['query']);assert.equal(s.properties.count.default,10);
 for(const count of [0,11,1.5])assert.throws(()=>translate('search_images',{query:'forest',count}));
 assert.throws(()=>translate('search_images',{query:'forest',page:2}));
 assert.throws(()=>translate('search_images',{query:'forest',orientation:'wide'}));
});
test('count default and bounds limit results without public pagination',async()=>{
 globalThis.fetch=async url=>{assert.equal(url.searchParams.get('page_size'),'10');return response(Array(12).fill(row));};
 assert.equal((await searchImages({},{query:'forest'})).results.length,10);
 globalThis.fetch=async url=>{assert.equal(url.searchParams.get('page_size'),'1');return response([row,row]);};
 assert.equal((await searchImages({},{query:'forest',count:1})).results.length,1);
});
test('all orientations translate to provider aspect ratio',async()=>{
 for(const [orientation,ratio] of Object.entries({landscape:'wide',portrait:'tall',squarish:'square'})) {
  globalThis.fetch=async url=>{assert.equal(url.searchParams.get('aspect_ratio'),ratio);return response([row]);};
  assert.equal((await searchImages({},{query:'forest',orientation})).orientation,orientation);
 }
});
test('Openverse attribution and license survive normalization',async()=>{
 globalThis.fetch=async()=>response([row]);
 const result=(await searchImages({},{query:'forest'})).results[0];
 for(const key of ['attribution','license','license_version','license_url','creator','creator_url','thumbnail','url'])assert.equal(result[key],row[key]);
 assert.equal(result.source_url,row.foreign_landing_url);
});
test('Unlimited Unsplash credentials stay in header; image and credit metadata survive',async()=>{
 globalThis.fetch=async(url,opts)=>{
  assert.equal(url.origin,'https://api.unsplash.com');assert.equal(opts.headers.Authorization,'Client-ID fixture-key');assert.ok(!url.href.includes('fixture-key'));assert.equal(opts.redirect,'manual');assert.equal(url.searchParams.get('orientation'),'portrait');
  return response([{id:'u',urls:{regular:row.url,thumb:row.thumbnail,small:row.thumbnail},user:{name:'Creator',links:{html:row.creator_url}},links:{html:row.foreign_landing_url,download_location:'https://api.unsplash.com/photos/u/download'}}]);
 };
 const r=await searchImages({UNSPLASH_ACCESS_KEY:'fixture-key'},{query:'portrait',orientation:'portrait'});
 assert.equal(r.provider,'Unsplash');assert.match(r.results[0].attribution,/Creator.*Unsplash/);assert.equal(r.results[0].license,'Unsplash License');assert.ok(r.results[0].download_location);
});
test('provider failure falls back without forwarding credentials',async()=>{
 let calls=0;globalThis.fetch=async(url,opts)=>{calls++;if(calls===1)return new Response('',{status:429});assert.equal(url.origin,'https://api.openverse.org');assert.deepEqual(opts.headers,{});return response([row]);};
 const r=await searchImages({UNSPLASH_ACCESS_KEY:'fixture-key'},{query:'forest'});assert.equal(calls,2);assert.deepEqual(r.fallback_from,['Unsplash']);
 globalThis.fetch=async()=>{throw new Error('fixture-key secret');};
 await assert.rejects(searchImages({UNSPLASH_ACCESS_KEY:'fixture-key'},{query:'forest'}),e=>!e.message.includes('fixture-key'));
});
test('malformed and oversized responses fail safely; successful empty search stays empty',async()=>{
 globalThis.fetch=async()=>new Response('{');await assert.rejects(searchImages({},{query:'forest'}));
 globalThis.fetch=async()=>new Response('x',{headers:{'content-length':'1048577'}});await assert.rejects(searchImages({},{query:'forest'}));
 globalThis.fetch=async()=>response([]);assert.deepEqual((await searchImages({},{query:'forest'})).results,[]);
});
test('skills support empty catalog, exact name and resource with independent provenance',()=>{
 assert.equal(loadSkill().skills.length,4);
 for(const skill of SKILLS){assert.equal(loadSkill({name:skill.name}).guidance,skill.guidance);assert.equal(loadSkill({name:skill.name,resource:'workflow'}).guidance,skill.guidance);assert.equal(skill.guidance,readFileSync(new URL('../../workflows/'+skill.name+'.md',import.meta.url),'utf8').replaceAll('\r\n','\n'));}
 assert.throws(()=>loadSkill({name:'unknown'}));assert.throws(()=>loadSkill({resource:'workflow'}));assert.throws(()=>loadSkill({name:'theme',resource:'unknown'}));
});
test('query loads a clear match and returns candidates for ambiguity or unknown tasks',()=>{
 assert.equal(loadSkill({query:'edit elementor layout'}).name,'builders');
 assert.equal(loadSkill({query:'sql database command'}).name,'cli');
 assert.equal(loadSkill({query:'theme content'}).candidates.length,2);
 assert.equal(loadSkill({query:'xyz'}).skills.length,4);
});
test('skill public schema permits catalog and requested lookup fields',()=>{
 assert.deepEqual(translate('load_skill',{}).args,{});
 assert.deepEqual(translate('load_skill',{name:'theme',resource:'workflow'}).args,{name:'theme',resource:'workflow'});
 assert.equal(translate('load_skill',{query:'theme'}).args.query,'theme');
 assert.throws(()=>translate('load_skill',{query:'theme',page:1}));
});
test('resource implementation contains no vendor endpoints, embedded credentials or hosted skill imports',()=>{
 const source=readFileSync(new URL('../src/resource-compatibility.js',import.meta.url),'utf8');
 assert.doesNotMatch(source,/https?:\/\/[^\s'"`]*wpvibe|Client-ID [A-Za-z0-9_-]{12,}|Bearer [A-Za-z0-9_-]{12,}/i);
 for(const s of SKILLS)assert.equal(s.source,'Independently authored Unlimited workflow');
 assert.doesNotMatch(source,/fetch\([^)]*(?:skill|resource)/i);
});
