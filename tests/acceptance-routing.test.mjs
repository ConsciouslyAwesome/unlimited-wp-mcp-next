// Strict acceptance regressions for verified 1.20.5 installation support.
// Keep these checks as regressions for the repaired endpoint routing.
import assert from 'node:assert/strict';
import {test,afterEach} from 'node:test';
import {canonicalURL} from '../gateway/src/security.js';
import {preflight} from '../gateway/src/connection-readiness.js';
const originalFetch=globalThis.fetch;
afterEach(()=>{globalThis.fetch=originalFetch;});

test('baseline acceptance: public HTTPS WordPress subdirectory can be registered',()=>{
  // Pinned connection-check.php address_row explicitly accepts different
  // WordPress/home addresses for subdirectory installations.
  const address='https://agency.example.com/wordpress';
  assert.equal(canonicalURL(address),address);
});

test('baseline acceptance: a healthy plain-permalink REST installation can pass readiness',async()=>{
  const site='https://agency.example.com';
  globalThis.fetch=async value=>{
    const url=new URL(value),route=url.searchParams.get('rest_route');
    if(route==='/')return new Response(JSON.stringify({namespaces:['wp/v2','unlimited-wp-mcp-next/v1']}));
    if(route==='/unlimited-wp-mcp-next/v1/connection/preflight')return new Response(JSON.stringify({plugin:'unlimited-wp-mcp-next',site_url:site,challenge:url.searchParams.get('challenge'),https:true,oauth_available:true,authorization_seen:true,oauth:{issuer:site,token_endpoint:site+'/?rest_route=/unlimited-wp-mcp-next/v1/oauth/token'}}));
    return new Response(JSON.stringify({code:'rest_no_route'}),{status:404});
  };
  // Pinned rest.php explicitly handles front-controller rest_route requests;
  // Unlimited WordPress metadata already supports a query-free legacy issuer.
  assert.equal((await preflight(site)).state,'ready');
});
