import {execFileSync} from 'node:child_process';
import {writeFileSync,readFileSync} from 'node:fs';
import {compatibilityCatalog,COMPATIBILITY_TARGETS,baselinePaths} from '../gateway/src/compatibility.js';
import {BASELINE_ROUTES} from '../gateway/src/baseline-routes.generated.js';
const sha='62bc77ccd1c15faf766f527a7935fe32f1b227d3';
const readme=execFileSync('git',['show',sha+':README.md'],{encoding:'utf8'});
const section=readme.split('## MCP tools your AI gets')[1].split('## Quick start')[0];
const documented=[...new Set([...section.matchAll(/`([a-z_]+)`/g)].map(m=>m[1]))];
const adapters=compatibilityCatalog().map(tool=>{
  const target=COMPATIBILITY_TARGETS[tool.name],path=baselinePaths[target],native=BASELINE_ROUTES[path]?.inputSchema;
  return {
    public_name:tool.name,internal_handler:target,
    name_evidence:documented.includes(tool.name)?'pinned README MCP table':tool.name==='code_snippet'?'pinned PHP handler; hosted advertisement unverified':'retained Unlimited extension',
    source_route:native?path:null,
    native_argument_matches:native?Object.keys(tool.inputSchema.properties).filter(k=>k!=='site_url'&&JSON.stringify(tool.inputSchema.properties[k])===JSON.stringify(native.properties[k])):[],
    input_schema:tool.inputSchema,
    unverifiable_hosted_fields:{description:'not present in GPL source; reconstructed from verified functionality',inputSchema:'exact MCP wrapper unavailable; native route fields used where shipped',annotations:'Unlimited handler annotations retained; hosted values unavailable',output:['search_images','load_skill'].includes(tool.name)?'independently authored resource response; hosted wrapper unavailable; see IMAGE-SKILL-PARITY.md':'native MCP content/error result preserved; closed wrapper formatting unverified',site_url:'explicit registry URL envelope chosen by Unlimited; pinned hosted field requirement unavailable',additionalProperties:'closed-object validation retained locally; exact hosted policy unknown'},
  };
});
const data={baseline:{version:'1.20.5',commit:sha,source:'https://github.com/awesomemotive/wpvibe-ai-mcp',authoritative_files:['README.md','includes/class-wpvibe-rest.php'],hosted_catalog_available:false},
  result:'Functional name/route compatibility, not byte-for-byte hosted MCP schema parity',
  documented_names:documented,documented_name_count:documented.length,advertised_tool_count:28,
  missing_documented_names:documented.filter(n=>!adapters.some(t=>t.public_name===n)&&!['connect_site','list_sites','remove_site'].includes(n)),
  exact_hosted_tool_contracts_verified:[],
  unchanged_site_management:['connect_site','list_sites','remove_site'],adapters,
  closed_service_gaps:['Exact hosted descriptions, MCP wrapper argument naming/defaults/required fields, annotations and output formatting are not in the pinned repository.','Hosted JavaScript browser rendering, browser navigation UX and wrapper read-file formatting/cache stubs are not provided by the PHP handlers.','Image search uses requested query/count/orientation with optional Unlimited-owned Unsplash credentials and Openverse fallback. Historic defaults and hosted output remain unverified; see IMAGE-SKILL-PARITY.md.','Skill catalog, deterministic query lookup and workflow resource adapters expose four independent local workflows. Hosted names/content/ranking/hierarchy remain unverified; see IMAGE-SKILL-PARITY.md.','Hosted approval panels/status polling and signed-worker UX are not recreated; native local browser approval and receipts remain. No later tools are inferred from the current live WPVibe catalog.'],
  remaining_schema_differences:['rest_api exposes existing local path/parameters plus documented GET/POST/PUT/DELETE; hosted route/body/query/fields/max_length shapes cannot be proved.','Abilities retain native name/input; hosted ability_name, hint flags or legacy input_json cannot be proved and are not inferred.','get_page_html retains native optional path; url/selector/max_length/JavaScript options are not inferred.','load_skill supports optional name/query/resource and no-argument local catalog; search_images supports query/count/orientation. These requested adapters do not prove the absent hosted schemas. Both retain optional site_url and require it when multiple connected sites make routing ambiguous.','Native runtime validation remains authoritative: Unlimited file content limits, array item validation, search bounds and safety refusals can be stricter than the raw GPL route metadata.','Operation receipt is an explicit retained Unlimited extension. Internal aliases remain callable with their existing site/approval_id shapes but are not advertised.','get_file_outline/read_file wrappers return existing native structured results; exact hosted line-number formatting and unchanged-cache behavior are unverified.'],
  local_migration:'gateway/drizzle/0001_interface_compat.sql adds encrypted pending-approval storage to the same existing D1 binding. Not applied to any hosted database.',
  unchanged_backend:['WordPress handlers','OAuth and PKCE','AES-GCM credential encryption','registered user/site/credential isolation','approval scope binding and one-use consumption','operation receipts and diagnostic redaction','no-quota behavior'],
};
const target=new URL('../MCP-PARITY.json',import.meta.url),text=JSON.stringify(data,null,2)+'\n';
if(process.argv.includes('--check')){if(readFileSync(target,'utf8').replaceAll('\r\n','\n')!==text)throw new Error('Interface report drifted');}else writeFileSync(target,text);
console.log('PASS interface report:',documented.length,'documented names;',adapters.length,'adapter entries; no unverifiable exact-match claims');
