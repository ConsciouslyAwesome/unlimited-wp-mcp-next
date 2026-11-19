// Disposable in-memory gateway for routing acceptance only.
import {DatabaseSync} from 'node:sqlite';
import {readFileSync} from 'node:fs';
import gateway from '../src/index.js';
const origin='https://gateway.example.com',user='routing-owner';
export function environment(){
  const sql=new DatabaseSync(':memory:');sql.exec('PRAGMA foreign_keys=ON');
  for(const file of ['0000_multisite.sql','0001_interface_compat.sql','0002_connection_observations.sql','0003_remove_example_seeds.sql'])sql.exec(readFileSync(new URL('../drizzle/'+file,import.meta.url),'utf8'));
  return {OAUTH_ENCRYPTION_KEY:Buffer.alloc(32,21).toString('base64'),DB:{sql,prepare(query){return{bind(...args){return{async first(){return sql.prepare(query).get(...args)||null;},async all(){return{results:sql.prepare(query).all(...args)};},async run(){return{meta:{changes:Number(sql.prepare(query).run(...args).changes)}};}};}};},async batch(stmts){sql.exec('BEGIN');try{const result=[];for(const s of stmts)result.push(await s.run());sql.exec('COMMIT');return result;}catch(e){sql.exec('ROLLBACK');throw e;}}}};
}
export async function rpc(env,name,args,who=user){return (await gateway.fetch(new Request(origin+'/mcp',{method:'POST',headers:{'content-type':'application/json','oai-authenticated-user-id':who},body:JSON.stringify({jsonrpc:'2.0',id:1,method:'tools/call',params:{name,arguments:args}})}),env)).json();}
