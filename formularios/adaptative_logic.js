
// adaptative_logic.js – Lógica adaptativa y microfeedback

document.addEventListener('change', function (e) {
  if (e.target.tagName === 'SELECT' && e.target.name.startsWith('pregunta_')) {
    const valor = parseInt(e.target.value);
    const tipo = e.target.dataset.tipo;
    const dim = e.target.dataset.valor;

    const zonaFeedback = document.getElementById('feedback');
    if (!zonaFeedback) return;

    // Feedback contextual adaptativo
    if (tipo === 'CONFLICTO' && dim === 'evasivo' && valor >= 4) {
      zonaFeedback.innerText = "🔎 Notamos que tiendes a evitar el conflicto directo. ¡Eso también puede ser una fortaleza!";
    } else if (tipo === 'DISC' && dim === 'D' && valor >= 4) {
      zonaFeedback.innerText = "🚀 Tu perfil muestra alta orientación a resultados.";
    } else if (tipo === 'MBTI' && dim === 'introvertido' && valor >= 4) {
      zonaFeedback.innerText = "🌱 Prefieres espacios tranquilos para pensar con claridad. ¡Eso es valioso!";
    } else if (tipo === 'HOFSTEDE' && dim === 'estructura' && valor >= 4) {
      zonaFeedback.innerText = "📊 Te sientes más cómodo con normas claras y procesos definidos.";
    } else {
      zonaFeedback.innerText = ""; // Limpia si no aplica
    }
  }
});
