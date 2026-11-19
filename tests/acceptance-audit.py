"""Read-only integrated release invariants; never connects to a hosted database."""
from pathlib import Path
import json, re

repo = Path(__file__).resolve().parents[1]
read = lambda p: (repo / p).read_text(encoding='utf-8')
version = '2.0.1'
assert re.search(r'^ \* Version: '+re.escape(version)+r'$', read('unlimited-wp-mcp-next.php'), re.M)
assert "define('UWNext_VERSION', '"+version+"');" in read('unlimited-wp-mcp-next.php')
assert 'Stable tag: '+version in read('readme.txt')
assert json.loads(read('gateway/package.json'))['version'] == version
assert "version:'"+version+"'" in read('gateway/src/index.js')
assert '**'+version+'**' in read('README.md')
assert '## '+version in read('CHANGELOG.md')
print('PASS 2.0.1 plugin/header/readme/package/gateway/documentation version consistency')

runtime = [*repo.joinpath('gateway/src').rglob('*.js'), *repo.joinpath('includes').rglob('*.php'), repo/'unlimited-wp-mcp-next.php']
for p in runtime:
    s = p.read_text(encoding='utf-8')
    assert not re.search(r'(?:mcp\.)?wpvibe\.ai|uwnext\.ai|BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY|OHRBXR8-KUfiZbwG9dlBppJsfFiX58eA5kq_AAP4mhw', s, re.I), p
    assert not re.search(r'https://(?:original|booking)\.example\.com', s), p
    if p.is_relative_to(repo/'gateway/src'):
        assert not re.search(r'\bDEFAULT_SITES\b|\bseed\s*\(|(?:from|import).*tests/', s), p
assert re.search(r"HEADER = '[!#$%&'*+.^_`|~0-9A-Za-z-]+';", read('includes/class-uwnext-approval-bypass.php'))
print('PASS no active example seeds, test imports, known vendor hosts/keys or invalid bypass header')

source = read('includes/class-uwnext-update-source.php')
repository = 'https://github.com/ConsciouslyAwesome/unlimited-wp-mcp-next'
assert 'Update URI: '+repository in read('unlimited-wp-mcp-next.php')
assert "REPOSITORY = '"+repository+"'" in source
assert 'https://api.github.com/repos/ConsciouslyAwesome/unlimited-wp-mcp-next/releases/latest' in source
assert "'unlimited-wp-mcp-next.zip'" in read('tests/updates.php')
report = json.loads(read('MCP-PARITY.json'))
assert report['missing_documented_names'] == []
assert report['exact_hosted_tool_contracts_verified'] == []
assert report['advertised_tool_count'] == 28
print('PASS consistent Unlimited update source; documented names present; no exact hosted-parity claim')

for name in ('content','theme','builders','cli'):
    assert (repo/'workflows'/f'{name}.md').is_file()
assert 'https://api.unsplash.com/search/photos' in read('gateway/src/resource-compatibility.js')
assert 'https://api.openverse.org/v1/images/' in read('gateway/src/resource-compatibility.js')
print('RESULT 4 integrated source invariant groups passed')
