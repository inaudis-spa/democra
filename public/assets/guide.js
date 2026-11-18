(() => {
  const onboarding = document.querySelector('[data-onboarding-banner]');
  const onboardingStorageKey = 'inaudis-onboarding-hidden';
  if (onboarding && localStorage.getItem(onboardingStorageKey) === '1') onboarding.hidden = true;
  document.querySelector('[data-hide-onboarding]')?.addEventListener('click', () => {
    onboarding.hidden = true;
    localStorage.setItem(onboardingStorageKey, '1');
  });

  const definitions = [
    { target: '[data-tour="welcome"]', title: 'Bienvenido a DEMOCRA', text: 'Este panel resume tus procesos. El recorrido señalará cada opción directamente en la pantalla para que sepas dónde encontrarla.' },
    { target: '[data-tour="new-election"]', title: 'Crea una votación', text: 'Comienza aquí. Define el nombre, las fechas y la modalidad del nuevo proceso electoral.' },
    { target: '[data-tour="elections"]', title: 'Configura tus procesos', text: 'En Votaciones encontrarás cada proceso y podrás entrar a su configuración, padrón y centro de control.' },
    { target: '[data-tour="control"]', optional: true, title: 'Opera desde el centro de control', text: 'Abre esta opción para gestionar preguntas, padrón, enlaces, apertura, cierre, resultados y fiscalización de esta votación.' },
    { target: '[data-tour="team"]', title: 'Administra el equipo', text: 'Aquí se crean y administran los accesos de organizadores y ministros de fe, respetando las funciones de cada perfil.' },
    { target: '[data-tour="audit"]', title: 'Revisa la trazabilidad', text: 'Auditoría permite comprobar quién realizó cada acción y cuándo ocurrió durante el proceso.' },
    { target: '[data-tour="guide"]', title: 'Consulta la guía cuando quieras', text: 'La guía completa explica el flujo según tu perfil. También puedes repetir este recorrido desde el botón “¿Cómo funciona?”.' }
  ];

  const steps = definitions.filter(step => {
    const target = document.querySelector(step.target);
    return target && !target.hidden && (!step.optional || target);
  });
  if (!steps.length) return;

  let current = 0;
  let activeTarget = null;
  const layer = document.createElement('div');
  layer.className = 'v-tour-layer';
  layer.hidden = true;
  layer.innerHTML = `
    <div class="v-tour-spotlight" aria-hidden="true"></div>
    <section class="v-tour-popover" role="dialog" aria-modal="true" aria-labelledby="tour-title" aria-describedby="tour-description">
      <span class="v-tour-arrow" aria-hidden="true"></span>
      <button class="v-tour-close" type="button" aria-label="Cerrar recorrido">×</button>
      <div class="v-tour-progress"></div>
      <small class="v-tour-count"></small>
      <h2 id="tour-title"></h2>
      <p id="tour-description"></p>
      <div class="v-tour-actions">
        <button class="v-link v-tour-skip" type="button">Salir</button>
        <div>
          <button class="v-btn v-tour-back" type="button">Anterior</button>
          <button class="v-btn v-btn-orange v-tour-next" type="button">Siguiente</button>
        </div>
      </div>
    </section>`;
  document.body.appendChild(layer);

  const spotlight = layer.querySelector('.v-tour-spotlight');
  const popover = layer.querySelector('.v-tour-popover');
  const margin = 12;
  const gap = 18;

  function place() {
    if (layer.hidden || !activeTarget) return;
    const rect = activeTarget.getBoundingClientRect();
    spotlight.style.left = `${Math.max(4, rect.left - 6)}px`;
    spotlight.style.top = `${Math.max(4, rect.top - 6)}px`;
    spotlight.style.width = `${Math.min(innerWidth - 8, rect.width + 12)}px`;
    spotlight.style.height = `${Math.min(innerHeight - 8, rect.height + 12)}px`;

    const width = Math.min(360, innerWidth - margin * 2);
    popover.style.width = `${width}px`;
    popover.classList.remove('arrow-top', 'arrow-bottom', 'arrow-left', 'arrow-right');
    const popHeight = popover.offsetHeight;
    let top;
    let left;

    if (rect.right + gap + width <= innerWidth - margin) {
      left = rect.right + gap;
      top = Math.max(margin, Math.min(rect.top + rect.height / 2 - popHeight / 2, innerHeight - popHeight - margin));
      popover.classList.add('arrow-left');
    } else if (rect.left - gap - width >= margin) {
      left = rect.left - width - gap;
      top = Math.max(margin, Math.min(rect.top + rect.height / 2 - popHeight / 2, innerHeight - popHeight - margin));
      popover.classList.add('arrow-right');
    } else if (rect.bottom + gap + popHeight <= innerHeight - margin) {
      top = rect.bottom + gap;
      left = Math.max(margin, Math.min(rect.left + rect.width / 2 - width / 2, innerWidth - width - margin));
      popover.classList.add('arrow-top');
    } else {
      top = Math.max(margin, rect.top - popHeight - gap);
      left = Math.max(margin, Math.min(rect.left + rect.width / 2 - width / 2, innerWidth - width - margin));
      popover.classList.add('arrow-bottom');
    }
    popover.style.left = `${left}px`;
    popover.style.top = `${top}px`;
  }

  function render() {
    const step = steps[current];
    activeTarget = document.querySelector(step.target);
    if (!activeTarget || activeTarget.hidden) {
      if (current < steps.length - 1) { current += 1; render(); }
      return;
    }
    activeTarget.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'center' });
    layer.querySelector('.v-tour-progress').innerHTML = steps.map((_, index) => `<i class="${index <= current ? 'active' : ''}"></i>`).join('');
    layer.querySelector('.v-tour-count').textContent = `PASO ${current + 1} DE ${steps.length}`;
    layer.querySelector('h2').textContent = step.title;
    layer.querySelector('p').textContent = step.text;
    layer.querySelector('.v-tour-back').hidden = current === 0;
    layer.querySelector('.v-tour-next').textContent = current === steps.length - 1 ? 'Finalizar' : 'Siguiente';
    window.setTimeout(place, 280);
  }

  function open() {
    current = 0;
    layer.hidden = false;
    document.body.classList.add('v-no-scroll');
    render();
    window.setTimeout(() => popover.focus(), 320);
  }

  function close() {
    layer.hidden = true;
    document.body.classList.remove('v-no-scroll');
    activeTarget = null;
    localStorage.setItem('inaudis-guide-seen', '1');
  }

  layer.querySelector('.v-tour-close').addEventListener('click', close);
  layer.querySelector('.v-tour-skip').addEventListener('click', close);
  layer.querySelector('.v-tour-back').addEventListener('click', () => { if (current > 0) { current -= 1; render(); } });
  layer.querySelector('.v-tour-next').addEventListener('click', () => {
    if (current === steps.length - 1) close();
    else { current += 1; render(); }
  });
  document.addEventListener('keydown', event => {
    if (layer.hidden) return;
    if (event.key === 'Escape') close();
    if (event.key === 'ArrowRight') layer.querySelector('.v-tour-next').click();
    if (event.key === 'ArrowLeft' && current > 0) layer.querySelector('.v-tour-back').click();
  });
  addEventListener('resize', place);
  document.querySelectorAll('[data-start-tour]').forEach(button => button.addEventListener('click', open));
  if (new URLSearchParams(location.search).get('tour') === '1' || !localStorage.getItem('inaudis-guide-seen')) window.setTimeout(open, 350);
})();
