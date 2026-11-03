from pathlib import Path
import json,sys
repo=Path(__file__).resolve().parents[1]
catalog=json.loads((repo.parent/'.runtime/next-generation-fixture/catalog.json').read_text(encoding='utf-8'))['result']['tools']
text='// Generated from the local next-generation WordPress catalog.\nexport const UPSTREAM_TOOLS = '+json.dumps(catalog,indent=2)+';\n'
path=repo/'gateway/src/upstream-tools.generated.js'
if '--check' in sys.argv:
    assert path.read_text(encoding='utf-8')==text,'Gateway catalog differs from the tested WordPress source'
else:path.write_text(text,encoding='utf-8')
print(f'PASS gateway catalog synchronized: {len(catalog)} tools')
