import {test} from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';

function engine(language='et') {
  const window={RenoveeriI18n:{language}};
  const ctx=vm.createContext({window});
  for(const file of ['renoveeri-support.js','renoveeri-ai-chat.js']) vm.runInContext(fs.readFileSync(`assets/js/${file}`,'utf8'),ctx);
  return window.RenoveeriAssistant;
}
function ask(e,text,state=e.createState()){return e.answer(text,state).html.replace(/\s+/g,' ');}

test('uses the published labour prices for all catalogue rows',()=>{
  const e=engine();
  const html=fs.readFileSync('hinnakiri.html','utf8');
  const published=[...html.matchAll(/<tr><td>([^<]+)<\/td><td>([^<]+)<\/td><td>al ([\d ,.]+) €<\/td>/g)];
  assert.equal(e.catalog.length,29);
  assert.equal(published.length,25);
  const normal=s=>s.toLowerCase().replaceAll(',','').replace(/\s+/g,' ').trim();
  for(const item of e.catalog.filter(p=>p.price!==null)){const row=published.find(r=>normal(r[1])===normal(item.name));assert.ok(row,item.name);assert.equal(item.price,Number(row[3].replaceAll(' ','').replace(',','.')),item.name);}
});
test('greeting does not swallow a specific question',()=>{const e=engine();assert.match(ask(e,'Tere, palju maksab laminaadi paigaldus 20 m²?'),/260 €/);});
test('follow-up amount works, but cannot leak into a new service',()=>{const e=engine(),s=e.createState();ask(e,'parketi paigaldus',s);assert.match(ask(e,'25 m²',s),/300 €/);assert.doesNotMatch(ask(e,'värvimine',s),/200 €/);assert.match(ask(e,'30 m²',s),/240 €/);});
test('keeps distinct quantities and units in combined work',()=>{const e=engine();const h=ask(e,'pahteldus 40 m², värvimine 30 m², liistud 12 jm');assert.match(h,/360 €/);assert.match(h,/240 €/);assert.match(h,/60 €/);assert.match(h,/660 €/);});
test('one ambiguous quantity is not applied to all jobs',()=>{const e=engine(),s=e.createState();const h=ask(e,'pahteldus värvimine 40 m²',s);assert.doesNotMatch(h,/orientiir kokku/);assert.match(ask(e,'25 m²',s),/Iga töö/);});
test('wrong unit cannot produce a total',()=>{const e=engine();assert.match(ask(e,'liistud 20 m²'),/ühik on jm/);assert.doesNotMatch(ask(e,'liistud 20 m²'),/100 €/);});
test('decimal commas, negative values and ranges',()=>{const e=engine();assert.match(ask(e,'laminaat 12,5 m²'),/162,5 €/);assert.match(ask(e,'parkett -20 m²'),/positiivne/);assert.doesNotMatch(ask(e,'parkett 20-30 m²'),/360 €/);});
test('a quoted project does not bypass its price estimate',()=>{const e=engine();assert.match(ask(e,'Soovin pakkumist: parkett 30 m²'),/360 €/);});
test('quote requests offer every public menu service in each language on every page',()=>{
  for(const language of ['et','en','ru']){
    const e=engine(language);
    const html=fs.readFileSync(language==='et'?'index.html':`${language}/index.html`,'utf8');
    const menu=html.match(/<ul class="submenu-items">([\s\S]*?)<\/ul>/)[1];
    const labels=[...menu.matchAll(/<a\b[^>]*>([^<]+)<\/a>/g)].map(m=>m[1]);
    assert.equal(labels.length,16);
    const result=e.answer(e.copy.chips[0],e.createState());
    for(const label of labels) assert.ok(result.suggestions.includes(label),`${language}: ${label}`);
    assert.ok(result.suggestions.includes(e.copy.chips[2]),`${language}: bathroom`);
    assert.equal(new Set(result.suggestions).size,result.suggestions.length);
    for(const label of result.suggestions){
      const reply=e.answer(label,e.createState());
      assert.ok(reply.html,`${language}: ${label}`);
      assert.notEqual(reply.html,e.copy.unknown,`${language}: ${label}`);
    }
  }
});
test('starting a fresh quote cannot reuse a previous job quantity',()=>{
  const e=engine(),s=e.createState();
  ask(e,'parkett 25 m²',s);
  const quote=e.answer(e.copy.chips[0],s);
  assert.match(quote.html,/Alustame töö liigist/);
  assert.match(ask(e,'25 m²',s),/Millist tööd/);
});
test('localized service choices distinguish repair from installation and facade from interior painting',()=>{
  for(const language of ['et','en','ru']){
    const e=engine(language),s=e.createState();
    const choices=e.answer(e.copy.chips[0],s).suggestions;
    assert.equal(e.answer(choices[6],s).html,e.copy.topics.repair);
    assert.equal(s.last.length,0);
    const facade=e.answer(choices[14],s);
    assert.match(facade.html,/14 €/);
    assert.equal(s.last[0],20);
  }
});
test('specific services do not include general services twice',()=>{const e=engine();assert.doesNotMatch(ask(e,'kalasabaparkett 20 m²'),/240 €/);assert.match(ask(e,'kalasabaparkett 20 m²'),/500 €/);assert.doesNotMatch(ask(e,'dušisegisti 1 tk'),/59 €/);assert.match(ask(e,'dušisegisti 1 tk'),/69 €/);assert.doesNotMatch(ask(e,'köögitausta plaatimine 1 komplekt'),/al 49 €/);});
test('materials, warranty and bookings use confirmed information',()=>{const e=engine();assert.match(ask(e,'Kas hind sisaldab materjale?'),/eraldi/);assert.match(ask(e,'Kas on garantii?'),/3-aastane/);assert.match(ask(e,'Kas saaksite homme alustada?'),/kinnitab meeskond/);});
test('bathroom and apartment packages are not multiplied by floor area',()=>{const e=engine();assert.match(ask(e,'vannitoa remont 6 m²'),/4 ?900 €/);assert.match(ask(e,'korteri remont 50 m²'),/8 ?900 €/);assert.match(ask(e,'50 m² korteri värvimine'),/põrandapind/);});
test('full service descriptions keep their quantity across conjunctions',()=>{const e=engine();assert.match(ask(e,'pahteldus ja lihvimine 40 m²'),/360 €/);});
test('structural and emergency questions never return a demolition quote',()=>{const e=engine();assert.match(ask(e,'kandva seina lammutus 10 m²'),/spetsialisti/);assert.doesNotMatch(ask(e,'kandva seina lammutus 10 m²'),/350 €/);assert.match(ask(e,'vesi uputab ja elektrijuhtmed on märjad'),/avarii/);});
test('does not pretend to upload photos or send requests',()=>{const e=engine();assert.match(ask(e,'Kuhu saata fotod?'),/kontaktivormis/);assert.match(ask(e,'Tahan inimesega rääkida'),/5551 5783/);assert.match(ask(e,'Kas oled inimene?'),/automaatne/);});
test('English and Russian prices and FAQs are localized',()=>{const en=engine('en'),ru=engine('ru');assert.match(ask(en,'laminate 20 m²'),/260 €/);assert.match(ask(ru,'ламинат 20 м²'),/260 €/);assert.match(ask(en,'Are materials included?'),/Materials/);assert.match(ask(ru,'Какая гарантия?'),/3 года/);assert.match(ask(ru,'паркет ёлочкой 20 м²'),/500 €/);});
test('user markup is never reflected as executable HTML',()=>{const e=engine();assert.doesNotMatch(ask(e,'<img src=x onerror=alert(1)> parkett 20 m²'),/<img/);});
test('material follow-up uses the quantity of the actual last job',()=>{const e=engine(),s=e.createState();ask(e,'plaatimine 20 m²',s);assert.match(ask(e,'Aga koos materjalidega?',s),/1180 €/);ask(e,'parkett 10 m²',s);assert.doesNotMatch(ask(e,'Aga koos materjalidega?',s),/1180 €/);});
test('technical comparisons receive advice rather than a labour calculation',()=>{const e=engine();assert.match(ask(e,'Mis vahe on laminaadil ja parketil?'),/aluspinna/);assert.match(ask(e,'Kas hüdroisolatsiooni on vaja?'),/enne plaatimist/);});
test('non-load-bearing demolition is distinguished from structural questions',()=>{const e=engine();assert.match(ask(e,'mittekandva seina lammutus 10 m²'),/350 €/);assert.match(ask(e,'Kas see sein on kandev?'),/kontroll/);});
test('jobs with no published prices are handed over without legacy prices',()=>{const e=engine();assert.match(ask(e,'tapeetimine 20 m²'),/puudub ühikuhind/);assert.match(ask(e,'katuse ehitus 90 m²'),/puudub ühikuhind/);});
test('all public language pages load the shared assistant in order without AI labels',()=>{
  const routes=JSON.parse(fs.readFileSync('scripts/i18n/routes.json','utf8'));
  for(const route of routes)for(const language of ['et','en','ru']){
    const file=language==='et'?route.source:route[language].endsWith('/')?route[language].slice(1)+'index.html':route[language].slice(1)+'.html';
    const html=fs.readFileSync(file,'utf8');assert.ok(html.indexOf('renoveeri-support.js')<html.indexOf('renoveeri-ai-chat.js'),file);assert.ok(html.includes('renoveeri-support.js'),file);assert.doesNotMatch(html,/<b>AI:<\/b>/,file);
  }
});
