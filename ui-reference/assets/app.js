
const btn=document.querySelector('[data-menu]');
const side=document.querySelector('.sidebar');
if(btn&&side){btn.addEventListener('click',()=>side.classList.toggle('open'));}
document.addEventListener('click',(e)=>{if(window.innerWidth<992 && side && side.classList.contains('open') && !side.contains(e.target) && !btn.contains(e.target)){side.classList.remove('open')}});
document.querySelectorAll('[data-calc-sale]').forEach(form=>{
 const q=form.querySelector('[name="quantity"]'), r=form.querySelector('[name="rate"]'), out=form.querySelector('[data-amount]');
 const calc=()=>{const a=(parseFloat(q.value||0)/1000)*parseFloat(r.value||0);out.textContent=a.toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2})+' USD'};
 q.addEventListener('input',calc);r.addEventListener('input',calc);calc();
});
document.querySelectorAll('[data-weigh]').forEach(form=>{
 const a=form.querySelector('[name="first_weight"]'), b=form.querySelector('[name="second_weight"]'), out=form.querySelector('[data-net]');
 const calc=()=>{const n=Math.max(0,parseFloat(a.value||0)-parseFloat(b.value||0));out.textContent=n.toLocaleString('en-US')+' kg'};
 a.addEventListener('input',calc);b.addEventListener('input',calc);calc();
});
