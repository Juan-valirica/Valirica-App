<?php
/**
 * denuncia_acuse.php — Acuse de recibo al denunciante no anónimo
 *
 * Variables: $nombre, $reference_code, $track_url, $country
 */

$titulo    = "Hemos recibido tu denuncia — {$reference_code}";
$preheader = "Tu denuncia ha sido registrada correctamente. Guarda tu código de referencia.";

// Texto legal según país
if ($country === 'ES') {
    $plazo_acuse      = '7 días hábiles';
    $plazo_resolucion = '90 días naturales';
    $ley_ref          = 'Ley 2/2023 de 20 de febrero (transposición Directiva UE 2019/1937)';
    $derechos_texto   = 'Tienes derecho a no sufrir represalias por haber presentado esta denuncia de buena fe. La empresa está obligada a garantizar tu protección conforme a la normativa vigente.';
} else {
    $plazo_acuse      = 'inmediato';
    $plazo_resolucion = '65 días naturales';
    $ley_ref          = 'Ley 1010/2006, Resolución 3461/2025 y Circular 0076/2025';
    $derechos_texto   = 'Tienes derecho a no ser objeto de represalias laborales por haber presentado esta denuncia. La empresa está obligada a investigar y resolver en los plazos establecidos.';
}

ob_start();
?>
<h1 style="margin:0 0 8px;font-size:26px;font-weight:700;color:#012133;font-family:Georgia,serif;line-height:1.3;">
  Denuncia registrada ✅
</h1>
<p style="margin:0 0 24px;font-size:15px;color:#7a7977;font-family:Georgia,serif;">
  Hola <?= htmlspecialchars($nombre) ?>, hemos recibido tu denuncia correctamente.
  Guarda el siguiente código para hacer seguimiento.
</p>

<!-- Código referencia -->
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:0 0 24px;">
  <tr>
    <td style="background-color:#012133;border-radius:12px;padding:24px;text-align:center;">
      <p style="margin:0 0 6px;font-size:12px;color:rgba(255,255,255,.6);font-family:Georgia,serif;text-transform:uppercase;letter-spacing:1px;">
        Tu código de referencia
      </p>
      <p style="margin:0;font-size:28px;font-weight:700;color:#ffffff;font-family:monospace;letter-spacing:3px;">
        <?= htmlspecialchars($reference_code) ?>
      </p>
    </td>
  </tr>
</table>

<!-- Plazos -->
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:0 0 24px;">
  <tr>
    <td style="background-color:#f7f6f4;border:1px solid #e8e6e3;border-radius:12px;padding:20px 24px;">
      <p style="margin:0 0 10px;font-size:13px;font-weight:700;color:#012133;font-family:Georgia,serif;text-transform:uppercase;letter-spacing:.6px;">
        ¿Qué ocurre ahora?
      </p>
      <p style="margin:0 0 8px;font-size:14px;color:#3d3c3b;font-family:Georgia,serif;line-height:1.6;">
        📬 Recibirás confirmación de inicio de instrucción en: <strong><?= $plazo_acuse ?></strong>
      </p>
      <p style="margin:0;font-size:14px;color:#3d3c3b;font-family:Georgia,serif;line-height:1.6;">
        📋 El canal tiene <strong><?= $plazo_resolucion ?></strong> para resolver tu denuncia.
      </p>
    </td>
  </tr>
</table>

<!-- Tus derechos -->
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:0 0 24px;">
  <tr>
    <td style="background-color:#D1FAE5;border:1px solid #A7F3D0;border-radius:10px;padding:14px 18px;">
      <p style="margin:0 0 4px;font-size:13px;font-weight:700;color:#065F46;font-family:Georgia,serif;">
        🛡 Tus derechos
      </p>
      <p style="margin:0;font-size:13px;color:#065F46;font-family:Georgia,serif;line-height:1.6;">
        <?= htmlspecialchars($derechos_texto) ?>
      </p>
    </td>
  </tr>
</table>

<!-- CTA seguimiento -->
<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 32px;">
  <tr>
    <td style="border-radius:12px;background-color:#EF7F1B;">
      <a href="<?= htmlspecialchars($track_url) ?>?code=<?= urlencode($reference_code) ?>"
         style="display:inline-block;padding:14px 32px;font-size:15px;font-weight:700;color:#ffffff;text-decoration:none;font-family:Georgia,serif;letter-spacing:0.3px;">
        Consultar estado de mi denuncia →
      </a>
    </td>
  </tr>
</table>

<hr style="border:none;border-top:1px solid #e8e6e3;margin:0 0 20px;" />

<p style="margin:0;font-size:12px;color:#9a9896;line-height:1.6;font-family:Georgia,serif;">
  Marco legal de referencia: <?= htmlspecialchars($ley_ref) ?>.<br>
  Este email es confidencial. No compartas tu código de referencia con terceros.<br>
  Si no reconoces haber enviado esta denuncia, ignora este mensaje.
</p>
<?php
$contenido = ob_get_clean();
include __DIR__ . '/base.php';
