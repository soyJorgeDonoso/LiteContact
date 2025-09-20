// Scroll suave para anclas
document.querySelectorAll('a[href^="#"]').forEach(a=>{
  a.addEventListener('click', e=>{
    const id = a.getAttribute('href');
    if(id.length>1){
      e.preventDefault();
      document.querySelector(id)?.scrollIntoView({behavior:'smooth', block:'start'});
      // Cierra menú móvil si está abierto
      document.querySelector('.nav ul')?.classList.remove('open');
    }
  });
});

// Validación mínima teléfono (8–9 dígitos)
const phone = document.getElementById('phone');
phone?.addEventListener('input', () => {
  phone.value = phone.value.replace(/\D/g,'').slice(0,9);
});

// Revelado on scroll con fallback
if ('IntersectionObserver' in window) {
  const io = new IntersectionObserver((entries)=>{
    entries.forEach(en=>{
      if(en.isIntersecting){ en.target.classList.add('show'); io.unobserve(en.target); }
    })
  }, {threshold:.15});
  document.querySelectorAll('.reveal').forEach(el=>io.observe(el));
} else {
  document.querySelectorAll('.reveal').forEach(el=>el.classList.add('show'));
}

// Popup inicial: aparece una vez por sesión
function openPopup(){
  const el = document.getElementById('popup');
  if(!el) return;
  el.classList.add('show');
  el.setAttribute('aria-hidden','false');
}
function closePopup(){
  const el = document.getElementById('popup');
  if(!el) return;
  el.classList.remove('show');
  el.setAttribute('aria-hidden','true');
  sessionStorage.setItem('popupSeen','1');
}
window.closePopup = closePopup;

function startBannerSlider(){
  try{
    const phrases = Array.from(document.querySelectorAll('[data-banner-phrase]'));
    if (!phrases.length){ setTimeout(startBannerSlider, 800); return; }
    phrases.forEach((el,idx)=>{ el.style.opacity = (idx===0? '1':'0'); el.style.transition='opacity .6s ease'; el.style.willChange='opacity'; });
    if (phrases.length > 1){
      let i = 0;
      const tick = ()=>{
        const cur = phrases[i];
        const nextIdx = (i+1) % phrases.length;
        const nxt = phrases[nextIdx];
        phrases.forEach((el,idx)=>{ el.style.opacity = (idx===nextIdx? '1':'0'); });
        i = nextIdx;
      };
      setTimeout(()=>{ tick(); setInterval(tick, 5000); }, 5000);
    }
  }catch(e){}
}

window.addEventListener('load', ()=>{
  if(!sessionStorage.getItem('popupSeen')){
    setTimeout(openPopup, 1200);
  }
  startBannerSlider();

  // Tema: aplicar preferencia guardada o sistema si no existe
  try{
    const root = document.documentElement;
    const saved = localStorage.getItem('theme'); // 'dark' | 'light' | null
    const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
    const shouldDark = saved ? (saved === 'dark') : prefersDark;
    root.classList.toggle('dark-theme', !!shouldDark);
    const btn = document.getElementById('themeToggle');
    if (btn){ btn.textContent = root.classList.contains('dark-theme') ? '☀️' : '🌙'; }
  }catch(_){}
});

// Validación y envío AJAX del formulario público
document.getElementById('btn_enviar_formulario_isapre')?.addEventListener('click', async (ev)=>{
  const form = ev.target.closest('form');
  if (!form) return;
  ev.preventDefault();
  const q = s=>form.querySelector(s);
  const serverAlert = document.getElementById('formServerAlert');
  if (serverAlert) { serverAlert.className = 'alert d-none'; serverAlert.textContent = ''; }
  const getFeedback = (el)=>{
    if (!el) return null;
    const descId = el.getAttribute('aria-describedby');
    if (descId) {
      const byId = document.getElementById(descId);
      if (byId) return byId;
    }
    let fb = el.nextElementSibling;
    if (fb && fb.classList && fb.classList.contains('invalid-feedback')) return fb;
    fb = document.createElement('div'); fb.className='invalid-feedback'; el.after(fb); return fb;
  }
  const setErr = (el, msg)=>{
    if (!el) return false;
    el.classList.remove('is-valid');
    el.classList.add('is-invalid');
    el.setAttribute('aria-invalid','true');
    const fb = getFeedback(el); if (fb) fb.textContent = msg; return true;
  }
  const setOk = (el)=>{ if(!el) return; el.removeAttribute('aria-invalid'); el.classList.remove('is-invalid'); el.classList.add('is-valid'); const fb=getFeedback(el); if(fb) fb.textContent=''; }
  const clr = el=>{ if(!el) return; el.removeAttribute('aria-invalid'); el.classList.remove('is-invalid','is-valid'); const fb=getFeedback(el); if(fb) fb.textContent=''; }

  // Validador básico de RUT chileno
  const validateRut = (rutRaw)=>{
    let rut = (rutRaw||'').toString().replace(/\./g,'').replace(/-/g,'').toUpperCase();
    if (rut.length < 2) return false;
    let cuerpo = rut.slice(0,-1); let dv = rut.slice(-1);
    if (!/^\d+$/.test(cuerpo)) return false;
    let suma=0, multip=2;
    for (let i=cuerpo.length-1;i>=0;i--){ suma += parseInt(cuerpo[i],10)*multip; multip = multip===7?2:multip+1; }
    let dvCalc = 11 - (suma % 11);
    let dvStr = dvCalc===11?'0': dvCalc===10? 'K': String(dvCalc);
    return dvStr === dv;
  }

  const name = q('#name'); const rut = q('#rut'); const age = q('#age');
  const phone = q('#phone'); const email = q('#email'); const commune = q('#commune'); const income = q('#income');
  const interest = q('#interest'); const isapre = q('#isapre');
  [name,rut,age,phone,email,commune,income,interest,isapre].forEach(clr);
  let ok = true;
  if (!name.value.trim()) { ok = !setErr(name,'Este campo es obligatorio'); } else setOk(name);
  if (!rut.value.trim()) { ok = !setErr(rut,'Este campo es obligatorio'); }
  else if (!validateRut(rut.value)) { ok = !setErr(rut,'RUT inválido'); } else setOk(rut);
  const ageVal = parseInt(age.value,10); if (!age.value.trim()) { ok = !setErr(age,'Este campo es obligatorio'); }
  else if (isNaN(ageVal) || ageVal<18 || ageVal>100) { ok = !setErr(age,'Edad entre 18 y 100'); } else setOk(age);
  phone.value = phone.value.replace(/\D/g,'').slice(0,9);
  if (!phone.value.trim()) { ok = !setErr(phone,'Este campo es obligatorio'); }
  else if (phone.value.length<8) { ok = !setErr(phone,'Teléfono 8–9 dígitos'); } else setOk(phone);
  const mailRx = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  if (!email.value.trim()) { ok = !setErr(email,'Este campo es obligatorio'); }
  else if (!mailRx.test(email.value)) { ok = !setErr(email,'El email no es válido'); } else setOk(email);
  if (!commune.value.trim()) { ok = !setErr(commune,'Debes seleccionar una comuna'); } else setOk(commune);
  if (!income.value.trim()) { ok = !setErr(income,'Este campo es obligatorio'); }
  else if (!/^\d+$/.test(income.value) || parseInt(income.value,10) <= 0) { ok = !setErr(income,'Renta numérica (>0)'); } else setOk(income);
  if (!interest.value || interest.value === '0') { ok = !setErr(interest,'Selecciona una opción'); } else setOk(interest);
  if (isapre.value === '') { ok = !setErr(isapre,'Selecciona tu isapre actual'); } else setOk(isapre);
  if (!ok) {
    // sin popup global: solo marcar campos
    const firstInvalid = form.querySelector('.is-invalid');
    firstInvalid?.scrollIntoView({behavior:'smooth', block:'center'});
    firstInvalid?.focus();
    return;
  }

  const btn = ev.currentTarget || ev.target;
  const btnOriginal = btn.textContent;
  btn.disabled = true; btn.textContent = 'Enviando…';
  const data = new FormData(form);
  const parseJSON = async (res)=>{
    const text = await res.text();
    try { return JSON.parse(text); }
    catch(e){ console.error('Respuesta no-JSON:', text); return { ok:false, mail:0, message:'Respuesta no válida del servidor' }; }
  };
  // Failsafe: reactivar botón si pasa mucho tiempo aun con errores inesperados
  const reenable = setTimeout(()=>{ try{ btn.disabled=false; btn.textContent = btnOriginal; }catch(_){} }, 17000);
  // AbortController opcional
  let ctrl = null; let timeoutId = null; let fetchInit = { method:'POST', body:data, headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'} };
  try {
    if (typeof AbortController !== 'undefined') {
      ctrl = new AbortController();
      timeoutId = setTimeout(()=>{ try{ ctrl.abort(); }catch(_){} }, 15000);
      fetchInit.signal = ctrl.signal;
    }
  } catch(_) {}
  try{
    const res = await fetch('includes/save_contact.php', fetchInit);
    const json = await parseJSON(res);
    if (json.ok) {
      if (window.Swal) {
        if (json.mail) {
          Swal.fire({icon:'success', title:'¡Registro exitoso!', text:'Gracias por registrarte. Te hemos enviado un correo de bienvenida.'});
        } else {
          Swal.fire({icon:'success', title:'¡Registro exitoso!', text:'Tu registro fue exitoso, pero no pudimos enviar el correo. Hemos guardado el mensaje en nuestros registros y nuestro equipo te contactará.'});
        }
      } else if (serverAlert) {
        serverAlert.className = 'alert alert-success';
        serverAlert.textContent = json.mail ? '✅ Gracias por registrarte. Te hemos enviado un correo de bienvenida.' : '⚠️ Tu registro fue exitoso, pero no pudimos enviar el correo. Hemos guardado el mensaje en nuestros registros y nuestro equipo te contactará.';
        serverAlert.classList.remove('d-none');
      }
      form.reset();
      [name,rut,age,phone,email,commune,income,interest,isapre].forEach(el=>el?.classList.remove('is-valid','is-invalid'));
      window.scrollTo({top: form.getBoundingClientRect().top + window.scrollY - 120, behavior:'smooth'});
    } else {
      if (serverAlert) { serverAlert.className = 'alert alert-danger'; serverAlert.textContent = json.message || '❌ Ocurrió un problema al procesar tu solicitud. Inténtalo nuevamente.'; serverAlert.classList.remove('d-none'); }
      if (window.Swal) {
        Swal.fire({icon:'error', title:'Error', text: json.message || 'No fue posible enviar el formulario.'});
      }
    }
  } catch(err){
    if (serverAlert) { serverAlert.className = 'alert alert-danger'; serverAlert.textContent = '❌ ' + (err?.message || 'No fue posible enviar el formulario.'); serverAlert.classList.remove('d-none'); }
  } finally {
    if (timeoutId) clearTimeout(timeoutId);
    clearTimeout(reenable);
    btn.disabled = false; btn.textContent = btnOriginal;
  }
});

// Evitar doble envío por Enter (submit nativo)
document.querySelector('form[action="includes/save_contact.php"]')?.addEventListener('submit', function(e){
  e.preventDefault();
  document.getElementById('btn_enviar_formulario_isapre')?.click();
});


// Toggle de tema manual (opcional)
(function(){
  const btn = document.getElementById('themeToggle');
  if (!btn) return;
  btn.addEventListener('click', ()=>{
    const root = document.documentElement;
    const isDark = root.classList.toggle('dark-theme');
    localStorage.setItem('theme', isDark ? 'dark' : 'light');
    btn.textContent = isDark ? '☀️' : '🌙';
  });
})();


