"""Run against an isolated disposable SQLite WordPress fixture; deny HTTP."""
from pathlib import Path
import json, os, shutil, subprocess, sys

repo = Path(__file__).resolve().parents[1]
workspace = repo.parent
core = workspace / '.runtime/wordpress'
fixture = workspace / '.runtime/next-generation-fixture'
assert core.is_dir()
fixture.mkdir(exist_ok=True)
content = fixture/'wp-content'
(content/'plugins').mkdir(parents=True, exist_ok=True)
(content/'themes').mkdir(exist_ok=True)
for name in ['sqlite-database-integration']:
    shutil.copytree(core/'wp-content/plugins'/name, content/'plugins'/name, dirs_exist_ok=True)
shutil.copy2(core/'wp-content/db.php', content/'db.php')
shutil.copytree(core/'wp-content/themes/twentytwentyfive', content/'themes/twentytwentyfive', dirs_exist_ok=True)
plugin = content/'plugins/unlimited-wp-mcp-next'
shutil.copytree(repo, plugin, dirs_exist_ok=True, ignore=shutil.ignore_patterns('.git','tests'))
php = workspace/'.runtime/php/php.exe'
lint=[]
for path in sorted(repo.rglob('*.php')):
    result=subprocess.run([str(php),'-l',str(path)],capture_output=True,text=True)
    lint.append({'file':str(path.relative_to(repo)),'exit':result.returncode,'output':result.stdout+result.stderr})
    if result.returncode:
        print(lint[-1]);sys.exit(1)
print(f'PASS PHP lint: {len(lint)} files')
result = subprocess.run([str(php),str(repo/'tests/integration.php'),str(core),str(fixture)],capture_output=True,text=True)
out=workspace/'audit/next-generation'
out.mkdir(exist_ok=True)
(out/'local-tests.txt').write_text(result.stdout+result.stderr,encoding='utf-8')
(out/'lint.json').write_text(json.dumps(lint,indent=2),encoding='utf-8')
print(result.stdout)
print(result.stderr)
if 'RESULT ' not in result.stdout:
    print('FAIL fixture did not reach test completion')
    sys.exit(1)
sys.exit(result.returncode)
