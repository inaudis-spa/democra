/* DEMOCRA: confirmación accesible, sin diálogos nativos del navegador. */
(function () {
  'use strict';
  function ask(message) {
    return new Promise(function (resolve) {
      const overlay = document.createElement('div');
      overlay.style.cssText = 'position:fixed;inset:0;z-index:10000;background:rgba(8,28,47,.72);display:flex;align-items:center;justify-content:center;padding:20px';
      const panel = document.createElement('section');
      panel.className = 'v-card v-pad';
      panel.setAttribute('role', 'dialog');
      panel.setAttribute('aria-modal', 'true');
      panel.setAttribute('aria-labelledby', 'democra-confirm-title');
      panel.style.cssText = 'background:#fff;width:min(100%,480px);max-height:90vh;overflow:auto';
      const heading = document.createElement('h2');
      heading.id = 'democra-confirm-title';
      heading.textContent = 'Confirmar acción';
      const description = document.createElement('p');
      description.className = 'v-muted';
      description.textContent = message;
      const actions = document.createElement('div');
      actions.style.cssText = 'display:flex;gap:12px;flex-wrap:wrap;margin-top:24px';
      const cancel = document.createElement('button');
      cancel.type = 'button'; cancel.className = 'v-btn v-btn-outline'; cancel.textContent = 'Cancelar';
      const accept = document.createElement('button');
      accept.type = 'button'; accept.className = 'v-btn v-btn-orange'; accept.textContent = 'Confirmar';
      cancel.style.flex = '1'; accept.style.flex = '1';
      actions.append(cancel, accept); panel.append(heading, description, actions); overlay.append(panel);
      const previous = document.activeElement;
      const priorOverflow = document.body.style.overflow;
      document.body.style.overflow = 'hidden';
      function finish(answer) {
        document.removeEventListener('keydown', onKey);
        overlay.remove(); document.body.style.overflow = priorOverflow;
        if (previous && previous.isConnected) previous.focus();
        resolve(answer);
      }
      function onKey(event) {
        if (event.key === 'Escape') { event.preventDefault(); finish(false); }
        if (event.key === 'Tab') {
          const target = document.activeElement === cancel ? accept : cancel;
          event.preventDefault(); target.focus();
        }
      }
      cancel.addEventListener('click', function () { finish(false); });
      accept.addEventListener('click', function () { finish(true); });
      overlay.addEventListener('click', function (event) { if (event.target === overlay) finish(false); });
      document.addEventListener('keydown', onKey);
      document.body.append(overlay); cancel.focus();
    });
  }
  window.DemocraConfirm = ask;
  document.addEventListener('submit', function (event) {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || !form.dataset.confirm || form.dataset.confirmed === '1') return;
    event.preventDefault();
    const submitter = event.submitter;
    ask(form.dataset.confirm).then(function (accepted) {
      if (!accepted) return;
      form.dataset.confirmed = '1';
      if (submitter && submitter.isConnected) form.requestSubmit(submitter);
      else form.requestSubmit();
    });
  }, true);
  document.addEventListener('click', function (event) {
    const button = event.target.closest('button[data-confirm-click]');
    if (!button) return;
    event.preventDefault();
    const form = button.form;
    if (!form) return;
    ask(button.dataset.confirmClick).then(function (accepted) {
      if (!accepted) return;
      const temporary = document.createElement('input');
      temporary.type = 'hidden'; temporary.name = button.name; temporary.value = button.value;
      if (button.name) form.append(temporary);
      form.dataset.confirmed = '1';
      form.requestSubmit();
    });
  }, true);
})();
