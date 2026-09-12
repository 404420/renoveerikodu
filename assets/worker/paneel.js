'use strict';
(() => {
  const form = document.getElementById('workday-form');
  if (!form) return;
  const serverDisabled = form.querySelector('[type=submit]').disabled;
  const date = document.getElementById('work-date');
  const start = document.getElementById('start');
  const end = document.getElementById('end');
  const pause = document.getElementById('pause');
  const output = document.getElementById('duration');
  const hint = document.getElementById('time-hint');
  const time = value => /^\d{2}:\d{2}$/.test(value) ? Number(value.slice(0,2))*60+Number(value.slice(3)) : NaN;
  const update = () => {
    end.setCustomValidity(''); pause.setCustomValidity('');
    const a=time(start.value), b=time(end.value), p=Number(pause.value);
    const gross=(b-a+1440)%1440;
    let error='';
    if (!Number.isFinite(a) || !Number.isFinite(b)) error='Vali algus ja lõpp';
    else if (gross === 0 || gross > 960) { error='Tööpäeva pikkus peab olema üle 0 ja kuni 16 tundi'; end.setCustomValidity(error); }
    else if (pause.value === '' || !Number.isInteger(p) || p < 0 || p >= gross) { error='Paus peab olema tööpäevast lühem'; pause.setCustomValidity(error); }
    if (error) { output.textContent='—'; hint.textContent=error; return; }
    const net=gross-p; output.textContent=`${Math.floor(net/60)} h ${net%60} min`;
    hint.textContent=b<a ? 'Lõpp järgmisel päeval · paus on maha arvestatud' : 'Paus on tööajast maha arvestatud';
  };
  [start,end,pause].forEach(input => input.addEventListener('input',update)); update();
  const buttons=document.querySelectorAll('[data-date]');
  const updateDates=()=>buttons.forEach(button=>{const active=button.dataset.date===date.value;button.classList.toggle('active',active);button.setAttribute('aria-pressed',String(active));});
  buttons.forEach(button=>button.addEventListener('click',()=>{date.value=button.dataset.date;updateDates();}));
  date.addEventListener('change',updateDates);updateDates();
  form.addEventListener('submit',event=>{update();if(!form.reportValidity()){event.preventDefault();return;}const submit=form.querySelector('[type=submit]');submit.disabled=true;submit.textContent='Salvestan…';});
  window.addEventListener('pageshow',()=>{const submit=form.querySelector('[type=submit]');if(!serverDisabled){submit.disabled=false;submit.textContent='Salvesta tööpäev ↗';}});
})();

(() => {
  const toggle=document.getElementById('profile-toggle'), menu=document.getElementById('profile-menu'), dialog=document.getElementById('profile-settings');
  toggle.addEventListener('click',()=>{menu.hidden=!menu.hidden;toggle.setAttribute('aria-expanded',String(!menu.hidden));});
  document.getElementById('open-settings').addEventListener('click',()=>{menu.hidden=true;toggle.setAttribute('aria-expanded','false');dialog.showModal();});
  document.getElementById('close-settings').addEventListener('click',()=>dialog.close());
  dialog.addEventListener('close',()=>toggle.focus());
  document.addEventListener('click',event=>{if(!menu.contains(event.target)&&!toggle.contains(event.target)){menu.hidden=true;toggle.setAttribute('aria-expanded','false');}});
  document.addEventListener('keydown',event=>{if(event.key==='Escape'){menu.hidden=true;toggle.setAttribute('aria-expanded','false');}});
})();

if (document.getElementById('profile-settings')?.dataset.reopen === 'yes') { document.getElementById('profile-settings').showModal(); }
