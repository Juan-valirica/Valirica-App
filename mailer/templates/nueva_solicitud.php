<?php
$tipoLabel = ($tipo === 'vacaciones') ? 'vacaciones' : 'un permiso';
$titulo    = htmlspecialchars($nombre_empleado) . " ha solicitado {$tipoLabel}";
$preheader = "Tienes una nueva solicitud de {$tipoLabel} pendiente de revisión.";

ob_start();
?>
<h1 style="margin:0 0 8px;font-size:26px;font-weight:700;color:#012133;font-family:Georgia,serif;line-height:1.3;">
  Nueva solicitud 📋
</h1>
<p style="margin:0 0 24px;font-size:15px;color:#7a7977;font-family:Georgia,serif;">
  Hola <?= htmlspecialchars($nombre_empleador) ?>, tienes una solicitud pendiente de revisión.
</p>

<!-- Detalle solicitud -->
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:0 0 28px;">
  <tr>
    <td style="background-color:#f7f6f4;border:1px solid #e8e6e3;border-radius:12px;padding:20px 24px;">
      <p style="margin:0 0 8px;font-size:14px;color:#9a9896;font-family:Georgia,serif;text-transform:uppercase;letter-spacing:0.8px;">
        Solicitud de <?= ($tipo === 'vacaciones') ? 'Vacaciones' : 'Permiso' ?>
      </p>
      <p style="margin:0 0 4px;font-size:18px;font-weight:700;color:#012133;font-family:Georgia,serif;">
        <?= htmlspecialchars($nombre_empleado) ?>
      </p>
      <p style="margin:0;font-size:14px;color:#3d3c3b;font-family:Georgia,serif;">
        📅 <?= htmlspecialchars($fechas) ?>
      </p>
    </td>
  </tr>
</table>

<p style="margin:0 0 32px;font-size:15px;color:#3d3c3b;line-height:1.7;font-family:Georgia,serif;">
  Accede al dashboard para aprobar o rechazar esta solicitud.
</p>

<!-- CTA -->
<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 32px;">
  <tr>
    <td style="border-radius:12px;background-color:#EF7F1B;">
      <a href="<?= htmlspecialchars($dashboard_url) ?>"
         style="display:inline-block;padding:14px 32px;font-size:15px;font-weight:700;color:#ffffff;text-decoration:none;font-family:Georgia,serif;letter-spacing:0.3px;">
        Revisar solicitud →
      </a>
    </td>
  </tr>
</table>

<hr style="border:none;border-top:1px solid #e8e6e3;margin:0 0 24px;" />

<p style="margin:0;font-size:13px;color:#9a9896;line-height:1.6;font-family:Georgia,serif;">
  Este email es un aviso automático. Accede a Valírica para gestionar la solicitud.
</p>
<?php
$contenido = ob_get_clean();
include __DIR__ . '/base.php';
