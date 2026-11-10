(()=>
{
    'use strict';const b=document.body,theme=document.querySelector('#theme'),lang=document.querySelector('#language');const stored=localStorage.getItem('tritonia-theme');const dark=stored?stored==='dark':matchMedia('(prefers-color-scheme: dark)').matches;b.classList.toggle('dark',dark);theme.textContent=dark?'☀':'☾';theme.addEventListener('click',()=>
    {
        b.classList.toggle('dark');localStorage.setItem('tritonia-theme',b.classList.contains('dark')?'dark':'light');theme.textContent=b.classList.contains('dark')?'☀':'☾'
    }
    );lang.addEventListener('change',()=>
    {
        const u=new URL(location.href);u.searchParams.set('lang',lang.value);location.href=u.toString()
    }
    );const out=document.querySelector('#new-date');if(out)
    {
        const fmt=s=>
        {
            const [y,m,d]=s.split('-');return `${d}.${m}.${y}`
        }
        ;const update=()=>
        {
            const val=Number(document.querySelector('input[name="years"]:checked')?.value||1);const [y,m,d]=out.dataset.expiry.split('-').map(Number);const now=new Date();const today=new Date(now.getFullYear(),now.getMonth(),now.getDate());const base=new Date(y,m-1,d)>today?new Date(y,m-1,d):today;const target=new Date(base.getFullYear()+val,base.getMonth(),base.getDate());const iso=`${target.getFullYear()}-${String(target.getMonth()+1).padStart(2,'0')}-${String(target.getDate()).padStart(2,'0')}`;out.textContent=fmt(iso);const btn=document.querySelector('button.primary');btn.disabled=iso>out.dataset.max;
        }
        ;document.querySelectorAll('input[name="years"]').forEach(x=>x.addEventListener('change',update));update();
    }
}
)();
