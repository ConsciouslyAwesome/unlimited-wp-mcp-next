"""Extract only GPL route metadata; never infer a closed hosted MCP schema."""
from pathlib import Path
import json, os, subprocess, sys, tempfile
repo=Path(__file__).resolve().parents[1]
sha='62bc77ccd1c15faf766f527a7935fe32f1b227d3'
source=subprocess.check_output(['git','-C',str(repo),'show',sha+':includes/class-wpvibe-rest.php'])
with tempfile.TemporaryDirectory(prefix='uwnext-interface-') as directory:
    pinned=Path(directory)/'pinned-rest.php'
    pinned.write_bytes(source)
    data=json.loads(subprocess.check_output([os.environ.get('PHP_BINARY','php'),str(repo/'tests/extract-baseline-routes.php'),str(pinned)]))
text='// Extracted without executing handlers from GPL WPVibe 1.20.5 class-wpvibe-rest.php, commit '+sha+'.\nexport const BASELINE_ROUTES = '+json.dumps(data,indent=2)+';\n'
dest=repo/'gateway/src/baseline-routes.generated.js'
if '--check' in sys.argv:
    assert dest.read_text()==text,'Pinned GPL route/schema metadata drifted'
else:
    dest.write_text(text)
print('PASS pinned GPL route/schema snapshot:',len(data),'routes')
