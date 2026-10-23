"""Static provenance and endpoint/command inventory against the pinned Git tree."""
from pathlib import Path
import hashlib, json, re, subprocess

repo=Path(__file__).resolve().parents[1]
base='62bc77ccd1c15faf766f527a7935fe32f1b227d3'
def git(*args):
    return subprocess.check_output(['git','-C',str(repo),*args])
paths=git('ls-tree','-r','--name-only',base).decode().splitlines()
hosts=[];routes=[];outbound=[]
for name in paths:
    value=git('show',f'{base}:{name}').decode('utf-8',errors='replace')
    for line_no,line in enumerate(value.splitlines(),1):
        if re.search(r'wpvibe[.]ai',line,re.I):
            hosts.append({'file':name,'line':line_no,'source':line})
        if 'register_rest_route(' in line:
            routes.append({'file':name,'line':line_no,'source':line.strip()})
        if re.search(r'wp_remote_(?:get|post|head|request)|curl_|fsockopen|PUBLIC_KEYS|Op_Proof::verify|X-WPVibe',line,re.I):
            outbound.append({'file':name,'line':line_no,'source':line.strip()})
source=git('show',f'{base}:includes/class-wpvibe-cli.php').decode()
allow=source.split('const ALLOWLIST = array(',1)[1].split('\n\t);',1)[0]
commands=re.findall(r"^\s*'([^']+)'\s*=>",allow,re.M)
now=(repo/'includes/class-uwnext-cli.php').read_text()
newallow=now.split('const ALLOWLIST = array(',1)[1].split('\n\t);',1)[0]
assert re.findall(r"^\s*'([^']+)'\s*=>",newallow,re.M)==commands
license_hashes={}
for name in ['LICENSE','third-party/svg-sanitize/LICENSE','starter-themes/classic/assets/js/alpine.min.js']:
    expected=git('show',f'{base}:{name}')
    actual=(repo/name).read_bytes()
    # Windows Git checks out CRLF; compare Git text without changing either file.
    assert actual.replace(b'\r\n',b'\n')==expected.replace(b'\r\n',b'\n'), f'notice/asset changed: {name}'
    license_hashes[name]=hashlib.sha256(actual).hexdigest()
assert license_hashes['LICENSE']=='498d32907f132c2cd7d3ae269f304aed87156293f63e724b83d9f62ed787d55a'
assert license_hashes['third-party/svg-sanitize/LICENSE']=='ab3fe34d3c62e3348015a4dd0128b09a5da2024d1467a478311003373c18a3f0'
runtime_refs=[]
for path in repo.rglob('*'):
    if not path.is_file() or any(p in {'.git','upstream-history','tests'} for p in path.relative_to(repo).parts):continue
    if path.suffix not in {'.php','.js','.css','.tpl'}:continue
    text=path.read_text(encoding='utf-8',errors='replace')
    if re.search(r'(?:mcp[.])?wpvibe[.]ai|uwnext[.]ai|OHRBXR8-KUfiZbwG9dlBppJsfFiX58eA5kq_AAP4mhw',text,re.I):runtime_refs.append(str(path.relative_to(repo)))
assert not runtime_refs,runtime_refs
fixture=repo.parent/'.runtime/next-generation-fixture'
catalog=json.loads((fixture/'catalog.json').read_text())['result']['tools']
manifest={'status':'VERIFIED static inventory, NOT functional parity acceptance','upstream_sha':base,'fork':'https://github.com/ConsciouslyAwesome/unlimited-wp-mcp-next','hosted_domain_references':hosts,'upstream_route_registrations':routes,'network_and_protocol_review_locations':outbound,'cli_commands':commands,'cli_command_count':len(commands),'catalog_count':len(catalog),'catalog':catalog,'unchanged_notice_hashes':license_hashes,'runtime_vendor_domain_or_key_matches':runtime_refs,'pending':['raw SQL and search-replace own-state protection','multi-site gateway integration','independent hosted image-search and skill content','detached execution and browser builder orchestration','upstream self-update replaced by independent release workflow','runtime builder/theme/CLI parity coverage']}
out=repo.parent/'audit/next-generation'
(out/'source-inventory.json').write_text(json.dumps(manifest,indent=2),encoding='utf-8')
print(f'PASS unchanged upstream notices; {len(commands)} CLI entries retained; {len(catalog)} tools; zero runtime vendor domains/keys')
