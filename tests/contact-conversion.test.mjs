import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import {readFileSync} from 'node:fs';

const source=readFileSync(new URL('../assets/js/main.js',import.meta.url),'utf8');
new vm.Script(source); // Validate the full production script as well as exercising its real handler.
const begin=source.indexOf("document.addEventListener('submit', async function(event)");
const end=source.indexOf('\n\t}, true);',begin);
assert.ok(begin>=0&&end>begin);
const submitSource=source.slice(begin,end+'\n\t}, true);'.length);

function formHarness({response={ok:true,success:true},captcha='valid-token',throwTracking=false,tracking=true,formId='contactForm'}={}) {
 const calls=[],events=[],handlers={};let requests=0,resets=0;
 const box={textContent:'',hidden:true,setAttribute(){}};
 const button={tagName:'BUTTON',textContent:'Saada',disabled:false,setAttribute(){}};
 const form={id:formId,parentNode:{insertBefore(){}},
  querySelector(selector){if(selector.includes('g-recaptcha-response'))return {value:captcha};return button;},
  querySelectorAll(){return [];},
  reset(){resets++;}};
 const document={addEventListener(type,fn){handlers[type]=fn;},getElementById(){return box;},
  dispatchEvent(event){events.push(event);return true;}};
 const window={location:{href:'https://www.renoveerikodu.ee/kontakt'},crypto:{randomUUID(){return '11111111-2222-3333-4444-555555555555';}}};
 if(tracking)window.gtag=(...args)=>{if(throwTracking)throw new Error('Tag blocked');calls.push(args);};
 const context=vm.createContext({document,window,language:'et',
  t:key=>key,Date,Math,CustomEvent:class{constructor(type,options){this.type=type;this.detail=options.detail;}},
  FormData:class{set(){}},
  fetch:async()=>{requests++;if(response.networkError)throw new TypeError('Offline');return {ok:response.ok,text:async()=>response.invalidJson?'not-json':JSON.stringify({success:response.success,message:response.message})};}});
 vm.runInContext(submitSource,context);
 return {calls,events,box,async send(){await handlers.submit({target:form,preventDefault(){},stopPropagation(){},stopImmediatePropagation(){}});},
 get requests(){return requests;},get resets(){return resets;}};
}
test('confirmed backend success fires one Ads conversion with no form data',async()=>{
 const h=formHarness();await h.send();assert.equal(h.calls.length,1);const [kind,name,p]=h.calls[0];
 assert.equal(kind,'event');assert.equal(name,'conversion');assert.equal(p.send_to,'AW-18466210438/4yw4CKPHr7gdEIaFsOVE');
 assert.deepEqual(Object.keys(p).sort(),['send_to','transaction_id']);assert.match(p.transaction_id,/^rk-/);
 assert.equal(h.resets,1);assert.equal(h.events.length,1);assert.equal(h.events[0].type,'rk:contact-success');
 assert.equal(h.events[0].detail.submission_id,p.transaction_id);assert.equal(h.box.textContent,'sendSuccess');
});
for (const [name,response] of [
 ['HTTP error',{ok:false,success:true}],
 ['backend rejects lead',{ok:true,success:false}],
 ['invalid JSON',{ok:true,invalidJson:true}],
 ['network failure',{networkError:true}]
])test(name+' does not fire any conversion',async()=>{
 const h=formHarness({response});await h.send();assert.equal(h.calls.length,0);assert.equal(h.events.length,0);assert.equal(h.resets,0);
});
test('empty CAPTCHA does not submit or count a lead',async()=>{
 const h=formHarness({captcha:''});await h.send();assert.equal(h.requests,0);assert.equal(h.calls.length,0);assert.equal(h.events.length,0);
});
test('newsletter submissions are ignored',async()=>{
 const h=formHarness({formId:'subscribeForm'});await h.send();assert.equal(h.requests,0);assert.equal(h.calls.length,0);
});
test('blocked Google tag does not turn a delivered lead into a form error',async()=>{
 const h=formHarness({throwTracking:true});await h.send();assert.equal(h.resets,1);assert.equal(h.box.textContent,'sendSuccess');assert.equal(h.events.length,1);
});
test('missing Google tag preserves successful form behaviour',async()=>{
 const h=formHarness({tracking:false});await h.send();assert.equal(h.resets,1);assert.equal(h.box.textContent,'sendSuccess');assert.equal(h.events.length,1);
});
