// inyectar_preguntas.js

// Este script carga y renderiza las preguntas del archivo JSON
document.addEventListener("DOMContentLoaded", async () => {
  const contenedor = document.getElementById("contenedor-pregunta");
  const btnEnviar = document.getElementById("btn-enviar");

  try {
    const res = await fetch("preguntas_valirica_170.php");
    const preguntasPorModulo = await res.json();

    preguntasPorModulo.forEach((modulo, moduloIndex) => {
      const moduloDiv = document.createElement("div");
      moduloDiv.className = "modulo";
      moduloDiv.style.display = moduloIndex === 0 ? "block" : "none";

      const titulo = document.createElement("h2");
      titulo.textContent = modulo.titulo;
      moduloDiv.appendChild(titulo);

      modulo.preguntas.forEach(pregunta => {
        const div = document.createElement("div");
        div.className = "pregunta";
        div.dataset.id = pregunta.id;

        const p = document.createElement("p");
        p.className = "enunciado";
        p.textContent = pregunta.texto;
        div.appendChild(p);

        pregunta.opciones.forEach(opcion => {
          const label = document.createElement("label");
          const input = document.createElement("input");
          input.type = "radio";
          input.name = `p${pregunta.id}`;
          input.value = opcion;
          label.appendChild(input);
          label.appendChild(document.createTextNode(" " + opcion));
          div.appendChild(label);
        });

        moduloDiv.appendChild(div);
      });

      contenedor.appendChild(moduloDiv);
    });

// Muestra el botón solo al final
const modulos = document.querySelectorAll('.modulo');
if (modulos.length > 0) {
  modulos[modulos.length - 1].appendChild(btnEnviar);
  btnEnviar.style.display = "inline-block";
}


  } catch (error) {
    contenedor.innerHTML = "<p style='color:red;'>Error cargando las preguntas.</p>";
    console.error("Error al cargar el JSON de preguntas:", error);
  }
});

window.dispatchEvent(new Event("preguntas-cargadas"));

