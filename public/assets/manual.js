(() => {
  const common = {
    dashboard: {icon:'⌂',category:'Plataforma',title:'Inicio',purpose:'Consulta el resumen de tus procesos.',objective:'Ubicar rápidamente votaciones, participación y tareas pendientes.',steps:['Presiona “Inicio” en el menú izquierdo.','Revisa las cifras principales y los procesos recientes.','Selecciona una votación para continuar en su Centro de control.'],result:'Ves únicamente la información disponible para tu perfil.',warning:'Si falta una votación, revisa que estés asignado al proceso.',focus:'Selecciona un proceso reciente.'},
    elections: {icon:'✓',category:'Plataforma',title:'Votaciones',purpose:'Encuentra y abre los procesos electorales.',objective:'Entrar al proceso correcto y reconocer su estado antes de actuar.',steps:['Presiona “Votaciones”.','Busca el proceso por su nombre y cliente.','Revisa si está en borrador, programado, en vivo o finalizado.','Presiona la tarjeta para abrirlo.'],result:'El nombre de la votación aparece bajo “Votación actual”.',warning:'Comprueba siempre el nombre antes de modificar o certificar algo.',focus:'Abre la tarjeta de la votación correcta.'},
    organizations: {icon:'▦',category:'Plataforma',title:'Organizaciones',purpose:'Crea y consulta los clientes de la plataforma.',objective:'Registrar correctamente a la organización que será dueña de sus votaciones.',steps:['Presiona “Organizaciones”.','Escribe el nombre y RUT del cliente.','Crea un identificador con minúsculas y guiones.','Presiona “Crear organización”.','Confirma que aparezca en la lista.'],result:'El nuevo cliente aparece activo y puede recibir usuarios y votaciones.',warning:'No crees dos organizaciones para el mismo cliente.',focus:'Completa “Nueva organización”.'},
    team: {icon:'♙',category:'Plataforma',title:'Equipo y accesos',purpose:'Crea y administra los accesos del equipo.',objective:'Dar a cada persona el perfil correcto sin concederle acceso a procesos ajenos.',steps:['Presiona “Equipo y accesos”.','Completa nombre, correo y contraseña inicial.','Selecciona Organizador, Ministro de Fe o Administrador.','Selecciona el cliente cuando corresponda.','Presiona “Crear usuario”.','Luego asigna el usuario en cada votación que corresponda.'],result:'La persona aparece activa en la lista de accesos.',warning:'Crear un usuario no le asigna votaciones automáticamente.',focus:'Crea el acceso y después realiza la asignación.'},
    audit: {icon:'◷',category:'Plataforma',title:'Auditoría general',purpose:'Revisa la trazabilidad disponible para tu perfil.',objective:'Saber quién realizó una acción, cuándo ocurrió y sobre qué elemento.',steps:['Presiona “Auditoría general”.','Busca la actividad que necesitas revisar.','Comprueba fecha, acción, responsable y dirección IP.','Si investigas un proceso específico, entra a su Auditoría del proceso.'],result:'Puedes reconstruir la secuencia de acciones sin modificarla.',warning:'La auditoría es de solo lectura y no debe eliminarse.',focus:'Revisa fecha, actividad y responsable.'},
    guide: {icon:'?',category:'Ayuda',title:'Cómo funciona',purpose:'Vuelve a este centro de aprendizaje cuando lo necesites.',objective:'Resolver dudas sin experimentar sobre una votación real.',steps:['Presiona “Cómo funciona”.','Selecciona tu perfil.','Abre la lección de la opción que deseas aprender.','Marca la lección cuando la comprendas.'],result:'Tu avance queda guardado en este navegador.',warning:'Puedes reiniciar el progreso sin afectar ningún dato del sistema.',focus:'Elige una lección del recorrido.'},
    control: {icon:'●',category:'Votación actual',title:'Centro de control',purpose:'Prepara, abre, supervisa y finaliza la votación.',objective:'Operar todo el proceso en el orden correcto desde una sola pantalla.',steps:['Confirma el nombre y estado del proceso.','Revisa papeleta, participantes, responsables y Ministro de Fe.','Selecciona la pregunta que se mostrará en sala.','Abre la votación solamente cuando todo esté aprobado.','Supervisa votos recibidos y pendientes.','Al terminar, presiona “Finalizar votación” y confirma.','Después del cierre revisa resultados, certificación y acta.'],result:'El estado y la actividad reciente reflejan cada acción ejecutada.',warning:'Abrir bloquea papeleta y padrón; finalizar no se puede deshacer.',focus:'Sigue los módulos 1, 2 y 3 en orden.'},
    configuration: {icon:'⚙',category:'Votación actual',title:'Configuración',purpose:'Construye y revisa la papeleta.',objective:'Dejar preguntas, alternativas y reglas listas antes de abrir.',steps:['Presiona “Configuración”.','Escribe una pregunta clara.','Selecciona respuesta única o múltiple.','Escribe una alternativa por línea.','Presiona “Agregar pregunta”.','Lee la papeleta completa como si fueras votante.'],result:'La pregunta y todas sus alternativas aparecen en la papeleta.',warning:'Después de abrir la votación ya no podrás editarla.',focus:'Agrega y revisa cada pregunta.'},
    census: {icon:'♙',category:'Votación actual',title:'Participantes',purpose:'Administra el padrón y las invitaciones.',objective:'Asegurar que solo las personas correctas estén habilitadas para votar.',steps:['Presiona “Participantes”.','Agrega personas manualmente o importa un CSV.','Revisa nombre, correo, peso y estado.','Corrige errores antes de abrir.','Con la votación abierta, presiona “Generar invitación”.','Entrega enlace y OTP mediante canales seguros.'],result:'Cada participante aparece como pendiente o votó.',warning:'El padrón queda bloqueado cuando se abre la votación.',focus:'Revisa “Personas habilitadas”.'},
    fiscalAdmin: {icon:'⚖',category:'Votación actual',title:'Fiscalización',purpose:'Asigna Ministros de Fe y consulta evidencias.',objective:'Separar la operación de la fiscalización y mantener evidencia verificable.',steps:['Presiona “Fiscalización”.','Selecciona un Ministro de Fe activo del mismo cliente.','Presiona “Asignar al proceso”.','Revisa la cadena de evidencias a medida que avanza la votación.'],result:'El Ministro aparece asignado y puede certificar sus propios hitos.',warning:'El encargado asigna; solo el Ministro de Fe certifica.',focus:'Asigna al responsable y revisa evidencias.'},
    results: {icon:'▥',category:'Votación actual',title:'Resultados',purpose:'Consulta participación y escrutinio definitivo.',objective:'Revisar el resultado sin exponer información antes del cierre.',steps:['Presiona “Resultados”.','Mientras esté abierta, revisa solamente participación.','Después del cierre, revisa votos por alternativa.','Comprueba que los totales sean coherentes.','Si eres encargado, certifica SHA-256 y genera el Acta PDF.'],result:'El resultado definitivo y su huella verificable quedan disponibles.',warning:'El detalle del escrutinio debe permanecer oculto hasta cerrar.',focus:'Certifica y descarga el acta después del cierre.'},
    processAudit: {icon:'◷',category:'Votación actual',title:'Auditoría del proceso',purpose:'Consulta solamente el historial de la votación seleccionada.',objective:'Verificar la secuencia de preparación, apertura, votación y cierre.',steps:['Presiona “Auditoría del proceso”.','Revisa aperturas, cierres, invitaciones y asignaciones.','Comprueba las certificaciones y sus responsables.','Anota el código y la fecha si investigas un incidente.'],result:'El historial mostrado pertenece únicamente al proceso actual.',warning:'No confundas esta vista con la Auditoría general.',focus:'Comprueba la secuencia completa del proceso.'},
    witnessFiscal: {icon:'⚖',category:'Votación actual',title:'Fiscalización',purpose:'Revisa y certifica cada hito del proceso.',objective:'Dejar evidencia SHA-256 de los hechos revisados personalmente.',steps:['Abre una votación asignada.','Lee el bloque “Siguiente hito”.','Comprueba personalmente la información indicada.','Presiona “Certificar este hito” cuando esté habilitado.','Confirma que la evidencia aparezca en la cadena.','Espera el estado correcto antes de continuar con el siguiente hito.'],result:'Cada hito aparece con fecha, Ministro de Fe y huella SHA-256.',warning:'Respeta el orden y nunca certifiques algo que no revisaste.',focus:'Certifica únicamente el siguiente hito disponible.'},
    voterAccess: {icon:'1',category:'Votante',title:'Abrir la invitación',purpose:'Accede de forma segura a tu votación.',objective:'Confirmar que el enlace corresponde al proceso esperado.',steps:['Abre el enlace personal que recibiste.','Comprueba que la pantalla muestre QUORUM.','Revisa el nombre de la votación.','No compartas el enlace con otra persona.'],result:'La pantalla solicita un código OTP de seis números.',warning:'Si el enlace es inválido, solicita uno nuevo al organizador.',focus:'Verifica el nombre antes de continuar.'},
    voterOtp: {icon:'2',category:'Votante',title:'Validar el código OTP',purpose:'Confirma tu acceso con un código temporal.',objective:'Habilitar la papeleta sin registrar todavía un voto.',steps:['Busca el código OTP recibido.','Escribe sus seis números.','Presiona “Validar y continuar”.','Si venció, solicita otro código.'],result:'La papeleta se muestra con sus preguntas y alternativas.',warning:'Varios intentos incorrectos pueden bloquear la invitación.',focus:'Ingresa exactamente los seis números.'},
    voterVote: {icon:'3',category:'Votante',title:'Emitir y comprobar el voto',purpose:'Selecciona, confirma y guarda tu comprobante.',objective:'Registrar tu preferencia una sola vez y conservar evidencia de recepción.',steps:['Lee la pregunta y todas las alternativas.','Marca tu preferencia.','Revisa cuidadosamente la selección.','Presiona “Confirmar voto”.','Acepta la confirmación final.','Guarda la huella SHA-256 o toma una captura.'],result:'La pantalla confirma que el voto fue registrado.',warning:'Después de confirmar no podrás modificar ni repetir el voto.',focus:'Guarda el comprobante final.'},
    logout: {icon:'↪',category:'Sesión',title:'Cerrar sesión',purpose:'Finaliza de forma segura tu acceso administrativo.',objective:'Evitar que otra persona utilice tu cuenta en el mismo equipo.',steps:['Busca tu nombre en la parte inferior del menú izquierdo.','Presiona “Cerrar sesión”.','Confirma que aparezca nuevamente la pantalla de acceso.','Si el equipo es compartido, cierra también el navegador.'],result:'El sistema vuelve al login y la sesión anterior deja de estar disponible.',warning:'No basta con cerrar la pestaña; utiliza siempre “Cerrar sesión”.',focus:'Presiona “Cerrar sesión” al terminar.'}
  };

  const profiles = {
    admin: {name:'Administrador',badge:'AD',lessons:['dashboard','elections','organizations','team','audit','guide','control','configuration','census','fiscalAdmin','results','processAudit','logout'],nav:['Inicio','Votaciones','Organizaciones','Equipo y accesos','Auditoría general','Cómo funciona','Cerrar sesión'],context:['Centro de control','Configuración','Participantes','Fiscalización','Resultados','Auditoría del proceso'],can:'crear clientes, usuarios y votaciones; asignar responsables y operar cualquier proceso.',cannot:'emitir votos administrativos ni certificar hitos en nombre de un Ministro de Fe.'},
    organizer: {name:'Organizador',badge:'OR',lessons:['dashboard','elections','team','audit','guide','control','configuration','census','fiscalAdmin','results','processAudit','logout'],nav:['Inicio','Votaciones','Equipo y accesos','Auditoría general','Cómo funciona','Cerrar sesión'],context:['Centro de control','Configuración','Participantes','Fiscalización','Resultados','Auditoría del proceso'],can:'preparar y operar solamente las votaciones donde fuiste asignado como encargado.',cannot:'ver procesos no asignados, crear clientes ni certificar hitos como Ministro de Fe.'},
    witness: {name:'Ministro de Fe',badge:'MF',lessons:['elections','witnessFiscal','results','processAudit','audit','guide','logout'],nav:['Votaciones','Auditoría general','Cómo funciona','Cerrar sesión'],context:['Fiscalización','Resultados','Auditoría del proceso'],can:'revisar procesos asignados, certificar tus hitos y consultar resultados y auditoría.',cannot:'editar papeletas o padrones, abrir o cerrar votaciones, asignar usuarios o certificar resultados administrativos.'},
    voter: {name:'Votante',badge:'VO',lessons:['voterAccess','voterOtp','voterVote'],nav:[],context:[],can:'validar tu invitación, marcar una preferencia y guardar el comprobante.',cannot:'entrar al panel administrativo, consultar otros votos o votar dos veces.'}
  };

  const locations = {
    dashboard:'Menú izquierdo → Inicio',elections:'Menú izquierdo → Votaciones',organizations:'Menú izquierdo → Organizaciones',team:'Menú izquierdo → Equipo y accesos',audit:'Menú izquierdo → Auditoría general',guide:'Menú izquierdo → Cómo funciona',control:'Votación actual → Centro de control',configuration:'Votación actual → Configuración',census:'Votación actual → Participantes',fiscalAdmin:'Votación actual → Fiscalización',witnessFiscal:'Votación actual → Fiscalización',results:'Votación actual → Resultados',processAudit:'Votación actual → Auditoría del proceso',voterAccess:'Enlace personal recibido',voterOtp:'Invitación → Validación OTP',voterVote:'Papeleta → Confirmación del voto',logout:'Parte inferior del menú → Cerrar sesión'
  };

  const quizzes = {
    dashboard:{q:'Si una votación no aparece en tu Inicio, ¿qué debes revisar primero?',o:['Que el usuario esté asignado al proceso','Crear nuevamente la votación','Cambiar el navegador'],c:0,e:'Inicio respeta las asignaciones. Crear duplicados no soluciona un problema de permisos.'},
    elections:{q:'¿Qué debes comprobar antes de abrir una tarjeta?',o:['El nombre y estado del proceso','La cantidad de administradores','El color de la pantalla'],c:0,e:'El nombre y el estado evitan operar por error una votación distinta.'},
    organizations:{q:'¿Quién puede crear una organización?',o:['Cualquier votante','Solo el Administrador','Solo el Ministro de Fe'],c:1,e:'Organizaciones es una función exclusiva del Administrador.'},
    team:{q:'Después de crear un Organizador, ¿ya ve todas las votaciones del cliente?',o:['Sí, automáticamente','No, debe ser asignado a cada votación','Solo si cambia su contraseña'],c:1,e:'La creación habilita las credenciales; la asignación define qué proceso puede administrar.'},
    audit:{q:'¿Para qué sirve Auditoría?',o:['Modificar votos','Reconstruir quién hizo cada acción y cuándo','Cambiar el estado de usuarios'],c:1,e:'Auditoría es un registro de solo lectura y trazabilidad.'},
    guide:{q:'¿El progreso de este manual cambia datos de una votación?',o:['Sí','No','Solo para el Administrador'],c:1,e:'El manual es una experiencia de aprendizaje independiente y segura.'},
    control:{q:'¿Qué ocurre al abrir una votación?',o:['Se eliminan las preguntas','Se bloquean papeleta y padrón','Se publican resultados'],c:1,e:'El bloqueo protege la configuración aprobada durante la recepción de votos.'},
    configuration:{q:'¿Cuándo debes terminar de editar la papeleta?',o:['Antes de abrir la votación','Después de recibir votos','Después de generar el acta'],c:0,e:'Una votación abierta ya no permite modificar preguntas ni alternativas.'},
    census:{q:'¿Cuándo aparece la acción “Generar invitación”?',o:['Cuando la votación está abierta','Mientras se crea la organización','Después de certificar resultados'],c:0,e:'Las invitaciones se generan para participantes pendientes durante una votación abierta.'},
    fiscalAdmin:{q:'¿Quién puede certificar los hitos del Ministro de Fe?',o:['Cualquier encargado','El propio Ministro de Fe asignado','Un votante'],c:1,e:'La separación de funciones impide que el encargado certifique en nombre del fiscalizador.'},
    witnessFiscal:{q:'¿Puede el Ministro certificar los hitos en cualquier orden?',o:['Sí','No, debe seguir el siguiente hito habilitado','Solo si la votación está en borrador'],c:1,e:'El sistema exige una secuencia y un estado válido para cada hito.'},
    results:{q:'¿Cuándo se muestra el escrutinio detallado?',o:['Antes de abrir','Durante la recepción de votos','Después del cierre'],c:2,e:'Durante la votación solo se informa participación; el detalle se protege hasta cerrar.'},
    processAudit:{q:'¿Qué diferencia tiene esta opción?',o:['Muestra solo la votación actual','Permite borrar eventos','Muestra todas las organizaciones'],c:0,e:'Auditoría del proceso limita la trazabilidad al proceso seleccionado.'},
    voterAccess:{q:'¿Puedes compartir tu enlace personal?',o:['Sí, con cualquier persona','No, identifica tu invitación','Solo después de votar'],c:1,e:'El enlace y el OTP son credenciales personales de acceso a la papeleta.'},
    voterOtp:{q:'¿Ingresar correctamente el OTP registra inmediatamente el voto?',o:['Sí','No, solo habilita la papeleta','Solo en voto público'],c:1,e:'El voto se registra únicamente después de seleccionar y confirmar una alternativa.'},
    voterVote:{q:'¿Qué debes hacer después de confirmar el voto?',o:['Volver a votar','Guardar la huella o comprobante','Compartir tu preferencia'],c:1,e:'La huella sirve como respaldo de recepción y no revela la preferencia.'},
    logout:{q:'¿Cuál es la forma correcta de terminar en un equipo compartido?',o:['Cerrar solamente la pestaña','Usar “Cerrar sesión” y cerrar el navegador','Dejar la pantalla abierta'],c:1,e:'Cerrar sesión invalida el acceso administrativo activo en ese navegador.'}
  };

  let profileKey = null;
  let lessonIndex = 0;
  const learning = document.querySelector('[data-learning]');
  const profileSection = document.querySelector('#elegir-perfil');
  const storageKey = 'quorum-manual-progress-v1';
  const returnPath = document.body.dataset.returnPath || '/login.php';
  const saved = JSON.parse(localStorage.getItem(storageKey) || '{}');
  document.querySelector('[data-total-lessons]').textContent = Object.keys(common).length;

  function lessons() { return profiles[profileKey].lessons.map(key => ({key,...common[key]})); }
  function completed() { return new Set(saved[profileKey] || []); }
  function persist(set) { saved[profileKey] = [...set]; localStorage.setItem(storageKey, JSON.stringify(saved)); }

  function renderNavigation() {
    const nav = document.querySelector('[data-course-nav]');
    const done = completed();
    nav.innerHTML = '';
    lessons().forEach((lesson,index) => {
      const button = document.createElement('button');
      button.type = 'button';
      button.className = `${index===lessonIndex?'active ':''}${done.has(lesson.key)?'done':''}`.trim();
      button.innerHTML = `<b>${index+1}</b><span>${lesson.title}</span><i>${done.has(lesson.key)?'✓':''}</i>`;
      button.addEventListener('click', () => { lessonIndex=index; render(); });
      nav.appendChild(button);
    });
  }

  function renderPreview(lesson) {
    const items = document.querySelector('[data-preview-items]');
    items.innerHTML = '';
    const shell=document.querySelector('.preview-shell');
    shell.classList.toggle('voter-mode',profileKey==='voter');
    const profile=profiles[profileKey];
    const labels=profileKey==='voter'?[]:[...profile.nav,...profile.context];
    labels.forEach(label => {
      const button = document.createElement('button');
      button.type = 'button';
      button.className = label===lesson.title ? 'active' : '';
      button.textContent = label;
      items.appendChild(button);
    });
    document.querySelector('[data-preview-kicker]').textContent=lesson.category;
    document.querySelector('[data-preview-title]').textContent=lesson.title;
    document.querySelector('[data-preview-description]').textContent=lesson.purpose;
    document.querySelector('[data-preview-focus]').textContent=lesson.focus;
  }

  function renderQuiz(lesson) {
    const quiz=quizzes[lesson.key];
    const options=document.querySelector('[data-quiz-options]');
    const feedback=document.querySelector('[data-quiz-feedback]');
    document.querySelector('[data-quiz-question]').textContent=quiz.q;
    options.innerHTML=''; feedback.hidden=true; feedback.textContent='';
    quiz.o.forEach((label,index)=>{
      const button=document.createElement('button');button.type='button';button.textContent=label;
      button.addEventListener('click',()=>{
        options.querySelectorAll('button').forEach((option,optionIndex)=>{option.disabled=true;if(optionIndex===quiz.c)option.classList.add('correct')});
        if(index!==quiz.c)button.classList.add('incorrect');
        feedback.innerHTML=`<strong>${index===quiz.c?'Respuesta correcta.':'Revisa esta respuesta.'}</strong> ${quiz.e}`;
        feedback.hidden=false;
      });
      options.appendChild(button);
    });
  }

  function render() {
    const profile = profiles[profileKey];
    const course = lessons();
    const lesson = course[lessonIndex];
    const done = completed();
    document.querySelector('[data-profile-badge]').textContent=profile.badge;
    document.querySelector('[data-profile-name]').textContent=profile.name;
    document.querySelector('[data-profile-name-copy]').textContent=profile.name;
    document.querySelector('[data-lesson-position]').textContent=`Lección ${lessonIndex+1} de ${course.length}`;
    document.querySelector('[data-lesson-icon]').textContent=lesson.icon;
    document.querySelector('[data-lesson-category]').textContent=lesson.category;
    document.querySelector('[data-lesson-title]').textContent=lesson.title;
    document.querySelector('[data-lesson-purpose]').textContent=lesson.purpose;
    document.querySelector('[data-lesson-access]').textContent=`Disponible para ${profile.name}`;
    document.querySelector('[data-lesson-location]').textContent=locations[lesson.key];
    document.querySelector('[data-profile-can]').textContent=profile.can;
    document.querySelector('[data-profile-cannot]').textContent=profile.cannot;
    document.querySelector('[data-lesson-objective]').textContent=lesson.objective;
    document.querySelector('[data-lesson-result]').textContent=lesson.result;
    document.querySelector('[data-lesson-warning]').textContent=lesson.warning;
    const steps=document.querySelector('[data-lesson-steps]'); steps.innerHTML='';
    lesson.steps.forEach(text => {const li=document.createElement('li');li.textContent=text;steps.appendChild(li)});
    const complete=document.querySelector('[data-complete]');
    complete.classList.toggle('done',done.has(lesson.key));
    complete.innerHTML=`<span>${done.has(lesson.key)?'●':'○'}</span> ${done.has(lesson.key)?'Aprendida':'Marcar como aprendida'}`;
    document.querySelector('[data-previous]').disabled=lessonIndex===0;
    document.querySelector('[data-next]').textContent=lessonIndex===course.length-1?'Finalizar recorrido ✓':'Siguiente →';
    const progress=Math.round(done.size*100/course.length);
    document.querySelector('[data-progress-label]').textContent=`${progress}%`;
    document.querySelector('[data-progress-bar]').style.width=`${progress}%`;
    renderNavigation(); renderPreview(lesson); renderQuiz(lesson);
  }

  function selectProfile(key) {
    profileKey=key; lessonIndex=0;
    document.querySelectorAll('[data-profile]').forEach(card=>card.classList.toggle('active',card.dataset.profile===key));
    learning.hidden=false; render();
    learning.scrollIntoView({behavior:'smooth',block:'start'});
  }

  document.querySelectorAll('[data-profile]').forEach(card=>card.addEventListener('click',()=>selectProfile(card.dataset.profile)));
  document.querySelector('[data-change-profile]').addEventListener('click',()=>profileSection.scrollIntoView({behavior:'smooth'}));
  document.querySelector('[data-previous]').addEventListener('click',()=>{if(lessonIndex>0){lessonIndex--;render();scrollToLesson()}});
  document.querySelector('[data-next]').addEventListener('click',()=>{const max=lessons().length-1;if(lessonIndex<max){lessonIndex++;render();scrollToLesson()}else openDialog(`<span class="manual-eyebrow">Recorrido completado</span><h2>Ya conoces el flujo de ${profiles[profileKey].name}</h2><p>Repasa las lecciones que todavía no hayas marcado o ingresa al sistema para comenzar.</p><a class="v-btn v-btn-primary" href="${returnPath}">Ir al sistema →</a>`)});
  document.querySelector('[data-complete]').addEventListener('click',()=>{const lesson=lessons()[lessonIndex];const done=completed();done.has(lesson.key)?done.delete(lesson.key):done.add(lesson.key);persist(done);render()});
  function scrollToLesson(){document.querySelector('.manual-lesson').scrollIntoView({behavior:'smooth',block:'start'})}

  const dialog=document.querySelector('[data-dialog]');
  function openDialog(html){document.querySelector('[data-dialog-content]').innerHTML=html;dialog.showModal()}
  document.querySelector('[data-close-dialog]').addEventListener('click',()=>dialog.close());
  dialog.addEventListener('click',event=>{if(event.target===dialog)dialog.close()});
  document.querySelector('[data-open-overview]').addEventListener('click',()=>openDialog('<span class="manual-eyebrow">Flujo general</span><h2>Una votación, de principio a fin</h2><ol><li>Crear cliente y usuarios.</li><li>Crear la votación y asignar responsables.</li><li>Configurar papeleta y padrón.</li><li>Fiscalizar la preparación.</li><li>Abrir y entregar invitaciones.</li><li>Recibir votos y supervisar participación.</li><li>Finalizar, fiscalizar y revisar resultados.</li><li>Certificar, generar acta y revisar auditoría.</li></ol>'));
  document.querySelector('[data-open-glossary]').addEventListener('click',()=>openDialog('<span class="manual-eyebrow">Palabras importantes</span><h2>Glosario simple</h2><dl><dt>Padrón</dt><dd>Lista de personas autorizadas para votar.</dd><dt>OTP</dt><dd>Código temporal de seis números.</dd><dt>Papeleta</dt><dd>Pantalla con las preguntas y alternativas.</dd><dt>Fiscalización</dt><dd>Revisión independiente de los hitos del proceso.</dd><dt>SHA-256</dt><dd>Huella digital que permite comprobar integridad.</dd><dt>Acta</dt><dd>Documento final consolidado de la votación.</dd></dl>'));
  document.querySelector('[data-reset-progress]').addEventListener('click',()=>{if(confirm('¿Reiniciar todo el progreso guardado en este navegador?')){localStorage.removeItem(storageKey);Object.keys(saved).forEach(key=>delete saved[key]);if(profileKey)render()}});

  const requested=new URLSearchParams(location.search).get('perfil');
  const sessionDefault=document.body.dataset.defaultProfile;
  const initialProfile=(requested&&profiles[requested])?requested:sessionDefault;
  if(initialProfile && profiles[initialProfile]) selectProfile(initialProfile);
})();
