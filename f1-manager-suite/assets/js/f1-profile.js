(function(){
  function disableForm(form){
    if (!form || form.__f1fp_disabled) return;
    form.__f1fp_disabled = true;
    var btns = form.querySelectorAll('button, input[type="submit"]');
    for (var i=0;i<btns.length;i++){
      try{ btns[i].setAttribute('disabled','disabled'); btns[i].classList.add('is-disabled'); btns[i].setAttribute('aria-disabled','true'); }catch(e){}
    }
  }
  var forms = document.querySelectorAll('.f1fp-wrap form');
  for (var f=0; f<forms.length; f++){
    (function(form){
      form.addEventListener('submit', function(){ disableForm(form); });
    })(forms[f]);
  }
  var delBtn   = document.getElementById('f1fp-delete-toggle');
  var delWrap  = document.getElementById('f1fp-delete-wrap');
  var expBtn   = document.getElementById('f1fp-export-toggle');
  var expWrap  = document.getElementById('f1fp-export-wrap');
  function closeDelete(){ if(!delBtn || !delWrap) return; delBtn.setAttribute('aria-expanded','false'); delWrap.setAttribute('hidden','hidden'); delBtn.textContent = delBtn.getAttribute('data-label-open') || 'Profil löschen'; }
  function openDelete(){ if(!delBtn || !delWrap) return; closeExport(); delBtn.setAttribute('aria-expanded','true'); delWrap.removeAttribute('hidden'); delBtn.textContent = delBtn.getAttribute('data-label-close') || 'Löschen abbrechen'; delWrap.scrollIntoView({behavior:'smooth', block:'start'}); }
  function closeExport(){ if(!expBtn || !expWrap) return; expBtn.setAttribute('aria-expanded','false'); expWrap.setAttribute('hidden','hidden'); expBtn.textContent = expBtn.getAttribute('data-label-open') || 'Datenexport'; }
  function openExport(){ if(!expBtn || !expWrap) return; closeDelete(); expBtn.setAttribute('aria-expanded','true'); expWrap.removeAttribute('hidden'); expBtn.textContent = expBtn.getAttribute('data-label-close') || 'Datenexport abbrechen'; expWrap.scrollIntoView({behavior:'smooth', block:'start'}); }
  if (delBtn && delWrap){ delBtn.addEventListener('click', function(){ var isOpen = delBtn.getAttribute('aria-expanded') === 'true'; if (isOpen) closeDelete(); else openDelete(); }); }
  if (expBtn && expWrap){ expBtn.addEventListener('click', function(){ var isOpen = expBtn.getAttribute('aria-expanded') === 'true'; if (isOpen) closeExport(); else openExport(); }); }
  var avForm  = document.getElementById('f1fp-avatar-form');
  var avClick = document.getElementById('f1fp-avatar-click');
  var fileInp = document.getElementById('f1fp_avatar_input');
  var remInp  = document.getElementById('f1fp_avatar_remove');
  var xBtn    = document.getElementById('f1fp-avatar-x');
  if (avClick && fileInp && remInp) { avClick.addEventListener('click', function(){ remInp.value = '0'; fileInp.click(); }); fileInp.addEventListener('change', function(){ if (fileInp.files && fileInp.files.length && avForm) { remInp.value = '0'; disableForm(avForm); avForm.submit(); } }); }
  if (xBtn && remInp && avForm && fileInp) { xBtn.addEventListener('click', function(e){ e.preventDefault(); e.stopPropagation(); if (!confirm('Profilbild wirklich entfernen?')) return; try { fileInp.value = ''; } catch(err){} remInp.value = '1'; disableForm(avForm); avForm.submit(); }); }
})();
