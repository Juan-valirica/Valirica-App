<?php
// Página de instrucciones para instalar la app
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Instalar App — Valírica</title>
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

  <!-- PWA Meta Tags -->
  <meta name="theme-color" content="#012133">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <meta name="apple-mobile-web-app-title" content="Valírica">
  <link rel="manifest" href="/manifest.json">
  <link rel="apple-touch-icon" href="https://app.valirica.com/uploads/logos/1749413056_logo-valirica.png">

  <link rel="stylesheet" href="valirica-design-system.css">

  <style>
    @import url("https://use.typekit.net/qrv8fyz.css");

    body {
      background: linear-gradient(135deg, #012133 0%, #184656 100%);
      min-height: 100vh;
      font-family: 'Gelica', system-ui, sans-serif;
      margin: 0;
      padding: 20px;
    }

    .container {
      max-width: 600px;
      margin: 0 auto;
      background: white;
      border-radius: 24px;
      padding: 40px 30px;
      box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    }

    .logo {
      text-align: center;
      margin-bottom: 30px;
    }

    .logo img {
      max-width: 180px;
      border-radius: 16px;
    }

    h1 {
      color: #012133;
      text-align: center;
      font-size: 1.8rem;
      margin-bottom: 10px;
    }

    .subtitle {
      text-align: center;
      color: #666;
      margin-bottom: 30px;
      font-size: 1rem;
    }

    .device-tabs {
      display: flex;
      gap: 10px;
      margin-bottom: 25px;
      justify-content: center;
    }

    .device-tab {
      padding: 12px 24px;
      border: 2px solid #e0e0e0;
      background: white;
      border-radius: 12px;
      cursor: pointer;
      font-weight: 600;
      font-size: 0.95rem;
      transition: all 0.3s;
    }

    .device-tab.active {
      background: #EF7F1B;
      color: white;
      border-color: #EF7F1B;
    }

    .instructions {
      display: none;
    }

    .instructions.active {
      display: block;
    }

    .step {
      display: flex;
      align-items: flex-start;
      gap: 16px;
      margin-bottom: 24px;
      padding: 20px;
      background: #f8f9fa;
      border-radius: 16px;
    }

    .step-number {
      background: #012133;
      color: white;
      width: 36px;
      height: 36px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: bold;
      flex-shrink: 0;
    }

    .step-content h3 {
      margin: 0 0 8px 0;
      color: #012133;
      font-size: 1.05rem;
    }

    .step-content p {
      margin: 0;
      color: #555;
      font-size: 0.95rem;
      line-height: 1.5;
    }

    .step-icon {
      font-size: 1.5rem;
      margin-right: 8px;
    }

    .cta-button {
      display: block;
      width: 100%;
      padding: 18px;
      background: linear-gradient(135deg, #EF7F1B 0%, #C65F00 100%);
      color: white;
      text-align: center;
      text-decoration: none;
      border-radius: 14px;
      font-weight: 600;
      font-size: 1.1rem;
      margin-top: 30px;
      box-shadow: 0 8px 24px rgba(239, 127, 27, 0.35);
      transition: transform 0.2s;
    }

    .cta-button:hover {
      transform: translateY(-2px);
    }

    .tip-box {
      background: #E8F5E9;
      border-left: 4px solid #4CAF50;
      padding: 16px;
      border-radius: 0 12px 12px 0;
      margin-top: 25px;
    }

    .tip-box strong {
      color: #2E7D32;
    }

    .tip-box p {
      margin: 8px 0 0 0;
      color: #1B5E20;
      font-size: 0.9rem;
    }

    @media (max-width: 480px) {
      .container {
        padding: 30px 20px;
      }

      .device-tabs {
        flex-direction: column;
      }

      .step {
        flex-direction: column;
        text-align: center;
      }
    }
  </style>
</head>
<body>
  <div class="container">
    <div class="logo">
      <img src="https://app.valirica.com/uploads/logos/1749413056_logo-valirica.png" alt="Valírica">
    </div>

    <h1>Instalar Valírica</h1>
    <p class="subtitle">Agrega la app a tu pantalla de inicio en 3 pasos</p>

    <div class="device-tabs">
      <button class="device-tab active" onclick="showInstructions('iphone')">iPhone / iPad</button>
      <button class="device-tab" onclick="showInstructions('android')">Android</button>
    </div>

    <!-- Instrucciones iPhone -->
    <div id="iphone-instructions" class="instructions active">
      <div class="step">
        <div class="step-number">1</div>
        <div class="step-content">
          <h3><span class="step-icon">🌐</span> Abre Safari</h3>
          <p>Visita <strong>app.valirica.com</strong> usando el navegador Safari (no Chrome ni otros).</p>
        </div>
      </div>

      <div class="step">
        <div class="step-number">2</div>
        <div class="step-content">
          <h3><span class="step-icon">📤</span> Toca el botón Compartir</h3>
          <p>En la barra inferior de Safari, toca el icono de compartir (cuadrado con flecha hacia arriba).</p>
        </div>
      </div>

      <div class="step">
        <div class="step-number">3</div>
        <div class="step-content">
          <h3><span class="step-icon">➕</span> Añadir a pantalla de inicio</h3>
          <p>Desplázate y selecciona <strong>"Añadir a pantalla de inicio"</strong>. Confirma tocando "Añadir".</p>
        </div>
      </div>

      <div class="tip-box">
        <strong>¡Listo!</strong>
        <p>La app aparecerá en tu pantalla de inicio con el logo de Valírica. Ábrela como cualquier otra app.</p>
      </div>
    </div>

    <!-- Instrucciones Android -->
    <div id="android-instructions" class="instructions">
      <div class="step">
        <div class="step-number">1</div>
        <div class="step-content">
          <h3><span class="step-icon">🌐</span> Abre Chrome</h3>
          <p>Visita <strong>app.valirica.com</strong> usando Google Chrome.</p>
        </div>
      </div>

      <div class="step">
        <div class="step-number">2</div>
        <div class="step-content">
          <h3><span class="step-icon">⋮</span> Toca el menú</h3>
          <p>Toca los tres puntos verticales (⋮) en la esquina superior derecha del navegador.</p>
        </div>
      </div>

      <div class="step">
        <div class="step-number">3</div>
        <div class="step-content">
          <h3><span class="step-icon">📲</span> Instalar aplicación</h3>
          <p>Selecciona <strong>"Instalar aplicación"</strong> o <strong>"Añadir a pantalla de inicio"</strong>. Confirma la instalación.</p>
        </div>
      </div>

      <div class="tip-box">
        <strong>¡Listo!</strong>
        <p>La app se instalará y aparecerá en tu pantalla de inicio. Funciona como una app nativa.</p>
      </div>
    </div>

    <a href="/login_equipo.php" class="cta-button">Ir a la App</a>
  </div>

  <script>
    function showInstructions(device) {
      // Ocultar todas las instrucciones
      document.querySelectorAll('.instructions').forEach(el => el.classList.remove('active'));
      document.querySelectorAll('.device-tab').forEach(el => el.classList.remove('active'));

      // Mostrar las instrucciones seleccionadas
      document.getElementById(device + '-instructions').classList.add('active');
      event.target.classList.add('active');
    }

    // Registrar Service Worker
    if ('serviceWorker' in navigator) {
      navigator.serviceWorker.register('/sw.js');
    }
  </script>
</body>
</html>
