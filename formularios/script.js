
// script.js – Control del formulario dinámico de Valírica

function mostrarModulo(i) {
  const modulos = document.querySelectorAll('.modulo');
  modulos.forEach((m, idx) => m.style.display = idx === i ? 'block' : 'none');

  // Actualiza barra de progreso
  const progreso = document.getElementById('progress-bar');
  if (progreso) {
    const porcentaje = Math.round(((i + 1) / modulos.length) * 100);
    progreso.style.width = porcentaje + '%';
    progreso.innerText = porcentaje + '% completado';
  }

  // Feedback general
  const feedbackZona = document.getElementById('feedback');
  if (feedbackZona) {
    const mensajes = [
      "¡Vamos bien! Estás comenzando a descubrir tu perfil.",
      "¡Buen ritmo! Esta parte nos muestra tu estilo cognitivo.",
      "¡Estás cerca! Este módulo analiza tu cultura personal.",
      "¡Último paso! Exploramos cómo gestionas los conflictos."
    ];
    feedbackZona.innerText = mensajes[i] || "";
  }
}

function siguienteModulo(i) {
  mostrarModulo(i);
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

function anteriorModulo(i) {
  mostrarModulo(i);
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

async function enviarFormulario(event) {
  event.preventDefault();

  const respuestas = [];

  document.querySelectorAll('.modulo').forEach((preguntaEl) => {
    const preguntaId = parseInt(preguntaEl.getAttribute('data-id'));
    const seleccionada = preguntaEl.querySelector('input[type="radio"]:checked');

    if (preguntaId && seleccionada) {
      respuestas.push({
        pregunta_id: preguntaId,
        respuesta: seleccionada.value
      });
    }
  });

  if (respuestas.length === 0) {
    alert("⚠️ Debes responder al menos una pregunta antes de enviar.");
    return;
  }

  try {
    const response = await fetch('procesar_formulario.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(respuestas)
    });

    const data = await response.text();
    alert(data);

    // Redirigir si fue exitoso
    if (data.includes("✅")) {
      window.location.href = "gracias.php"; // Cambia a la página que desees
    }

  } catch (error) {
    alert("❌ Hubo un error al enviar el formulario.");
    console.error(error);
  }
}

function actualizarProgresoPorRespuestas() {
  const totalPreguntas = document.querySelectorAll('.modulo').length;
  const respondidas = Array.from(document.querySelectorAll('.modulo')).filter(p => 
    p.querySelector('input[type="radio"]:checked')
  ).length;

  const porcentaje = Math.round((respondidas / totalPreguntas) * 100);

  const progreso = document.getElementById('progress-bar');
  if (progreso) {
    progreso.style.width = porcentaje + '%';
    progreso.innerText = porcentaje + '% completado';
  }
}



window.addEventListener("preguntas-cargadas", () => {
  setTimeout(() => mostrarModulo(0), 500); // Espera medio segundo para asegurar que las preguntas ya estén en el DOM

  actualizarProgresoPorRespuestas(); // Calcula el progreso inicial

  document.querySelectorAll('input[type="radio"]').forEach(radio => {
    radio.addEventListener('change', actualizarProgresoPorRespuestas);
  });

  const formulario = document.querySelector("form");
  if (formulario) {
    formulario.addEventListener("submit", enviarFormulario);
  }
});

let moduloActual = 0;

document.getElementById("btnSiguiente")?.addEventListener("click", () => {
  const total = document.querySelectorAll(".modulo").length;
  if (moduloActual < total - 1) {
    moduloActual++;
    siguienteModulo(moduloActual);
  }
});

document.getElementById("btnAnterior")?.addEventListener("click", () => {
  if (moduloActual > 0) {
    moduloActual--;
    anteriorModulo(moduloActual);
  }
});
