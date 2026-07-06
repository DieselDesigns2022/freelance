document.addEventListener('DOMContentLoaded', () => {
  const images = [...document.querySelectorAll('[data-lightbox-src]')];
  if (!images.length) return;
  const modal = document.createElement('div');
  modal.className = 'lightbox';
  modal.innerHTML = '<button class="lightbox-close" aria-label="Close image">×</button><button class="lightbox-prev" aria-label="Previous image">‹</button><img alt=""><button class="lightbox-next" aria-label="Next image">›</button>';
  document.body.appendChild(modal);
  const img = modal.querySelector('img');
  let index = 0;
  function show(i){ index=(i+images.length)%images.length; img.src=images[index].dataset.lightboxSrc; img.alt=images[index].dataset.lightboxAlt || ''; modal.classList.add('open'); }
  function close(){ modal.classList.remove('open'); img.removeAttribute('src'); }
  images.forEach((el,i)=>el.addEventListener('click',()=>show(i)));
  modal.querySelector('.lightbox-close').addEventListener('click', close);
  modal.querySelector('.lightbox-prev').addEventListener('click', e=>{e.stopPropagation(); show(index-1);});
  modal.querySelector('.lightbox-next').addEventListener('click', e=>{e.stopPropagation(); show(index+1);});
  modal.addEventListener('click', e=>{ if(e.target===modal) close(); });
  document.addEventListener('keydown', e=>{ if(e.key==='Escape') close(); if(modal.classList.contains('open') && e.key==='ArrowLeft') show(index-1); if(modal.classList.contains('open') && e.key==='ArrowRight') show(index+1); });
});
