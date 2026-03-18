<?php
/**
 * denuncia_nueva.php — Notificación al responsable: nueva denuncia recibida
 *
 * Variables: $nombre_responsable, $reference_code, $tipo, $country, $manage_url
 */

$flag        = $country === 'CO' ? '🇨🇴' : '🇪🇸';
$pais        = $country === 'CO' ? 'Colombia' : 'España';
$titulo      = "Nueva denuncia recibida — {$reference_code}";
$preheader   = "Se ha registrado una nueva denuncia ({$tipo}). Código: {$reference_code}";

$legal_texto = $country === 'ES'
    ? 'Según la Ley 2/2023, tienes <strong>7 días hábiles</strong> para enviar el acuse de recibo y <strong>90 días naturales</strong> para resolver.'
    : 'Según la Resolución 3461/2025, el acuse es <strong>inmediato</strong> y el plazo de resolución es de <strong>65 días naturales</strong>.';

ob_start();
?>
<h1 style="margin:0 0 8px;font-size:26px;font-weight:700;color:#012133;font-family:Georgia,serif;line-height:1.3;">
  Nueva denuncia recibida 🔔
</h1>
<p style="margin:0 0 24px;font-size:15px;color:#7a7977;font-family:Georgia,serif;">
  Hola <?= htmlspecialchars($nombre_responsable) ?>, se ha registrado una nueva denuncia en tu canal.
</p>

<!-- Detalle -->
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:0 0 24px;">
  <tr>
    <td style="background-color:#f7f6f4;border:1px solid #e8e6e3;border-radius:12px;padding:20px 24px;">
      <p style="margin:0 0 6px;font-size:12px;color:#9a9896;font-family:Georgia,serif;text-transform:uppercase;letter-spacing:0.8px;">
        Código de referencia
      </p>
      <p style="margin:0 0 14px;font-size:22px;font-weight:700;color:#012133;font-family:monospace;letter-spacing:2px;">
        <?= htmlspecialchars($reference_code) ?>
      </p>
      <p style="margin:0 0 4px;font-size:14px;color:#3d3c3b;font-family:Georgia,serif;">
        📋 Tipo: <strong><?= htmlspecialchars($tipo) ?></strong>
      </p>
      <p style="margin:0;font-size:14px;color:#3d3c3b;font-family:Georgia,serif;">
        <?= $flag ?> País: <strong><?= htmlspecialchars($pais) ?></strong>
      </p>
    </td>
  </tr>
</table>

<!-- Aviso legal -->
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:0 0 24px;">
  <tr>
    <td style="background-color:#EFF6FF;border:1px solid #BFDBFE;border-radius:10px;padding:14px 18px;">
      <p style="margin:0;font-size:13px;color:#1E40AF;font-family:Georgia,serif;line-height:1.6;">
        ⚖️ <?= $legal_texto ?>
      </p>
    </td>
  </tr>
</table>

<p style="margin:0 0 28px;font-size:15px;color:#3d3c3b;line-height:1.7;font-family:Georgia,serif;">
  Accede al panel de gestión para revisar el expediente y actuar dentro de los plazos legales.
</p>

<!-- CTA -->
<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 32px;">
  <tr>
    <td style="border-radius:12px;background-color:#EF7F1B;">
      <a href="<?= htmlspecialchars($manage_url) ?>"
         style="display:inline-block;padding:14px 32px;font-size:15px;font-weight:700;color:#ffffff;text-decoration:none;font-family:Georgia,serif;letter-spacing:0.3px;">
        Gestionar denuncia →
      </a>
    </td>
  </tr>
</table>

<hr style="border:none;border-top:1px solid #e8e6e3;margin:0 0 20px;" />

<p style="margin:0;font-size:12px;color:#9a9896;line-height:1.6;font-family:Georgia,serif;">
  Este aviso es <strong>confidencial</strong> y está dirigido exclusivamente al responsable del canal de denuncias.<br>
  No compartas este correo ni el código de referencia con terceros no autorizados.
</p>
<?php
$contenido = ob_get_clean();
include __DIR__ . '/base.php';
