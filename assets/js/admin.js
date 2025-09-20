// Paginación básica por tabla (si en el futuro se usa en cliente)
function paginateTable(tableSelector, rowsPerPage){
  const table = document.querySelector(tableSelector);
  if(!table) return;
  const rows = Array.from(table.querySelectorAll('tbody tr'));
  let page = 0;
  function render(){
    rows.forEach((r,i)=>{ r.style.display = (Math.floor(i/rowsPerPage)===page) ? '' : 'none'; });
  }
  render();
  return {
    next(){ page = Math.min(Math.floor((rows.length-1)/rowsPerPage), page+1); render(); },
    prev(){ page = Math.max(0, page-1); render(); }
  };
}


// Tema oscuro opcional para Admin
(function(){
  try{
    const root = document.documentElement;
    const saved = localStorage.getItem('admin-theme'); // 'dark' | 'light' | null
    const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
    const shouldDark = saved ? (saved === 'dark') : prefersDark;
    root.classList.toggle('dark-theme', !!shouldDark);
    // Inserta toggle si hay header admin
    const hdr = document.querySelector('.admin-header .wrap');
    if (hdr){
      // Evitar duplicados si ya existe
      if (document.getElementById('themeToggle')) return;
      const btn = document.createElement('button');
      btn.className = 'btn btn-compact';
      btn.style.marginLeft = '12px';
      btn.title = 'Cambiar tema';
      btn.setAttribute('aria-label','Cambiar tema');
      btn.id = 'themeToggle';
      btn.textContent = root.classList.contains('dark-theme') ? '☀️' : '🌙';
      btn.addEventListener('click', ()=>{
        const isDark = root.classList.toggle('dark-theme');
        localStorage.setItem('admin-theme', isDark ? 'dark' : 'light');
        btn.textContent = isDark ? '☀️' : '🌙';
      });
      // Colocar al final del nav si existe, si no, al final del wrap
      const nav = hdr.querySelector('nav');
      (nav || hdr).appendChild(btn);
    }
  }catch(_){}
})();

// Marcar sección activa y abrir grupo
(function(){
  try{
    const here = location.pathname.split('/').pop().toLowerCase();
    const nav = document.querySelector('.navbar, .admin-menu');
    if (!nav) return;
    const links = nav.querySelectorAll('a[href]');
    links.forEach(a=>{
      const href = (a.getAttribute('href')||'').toLowerCase();
      if (!href) return;
      if (here === href){
        a.classList.add('active');
        const dd = a.closest('.dropdown');
        if (dd){ dd.querySelector('[data-bs-toggle="dropdown"]')?.classList.add('active'); }
      }
    });
    // Evitar duplicar botón de tema: si hay más de uno, remover duplicados extra
    const toggles = document.querySelectorAll('#themeToggle');
    if (toggles.length > 1){ toggles.forEach((btn,idx)=>{ if (idx>0) btn.remove(); }); }
  }catch(_){ }
})();

