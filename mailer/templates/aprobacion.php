<?php
$esAprobado  = ($estado === 'aprobado');
$tipoLabel   = ($tipo === 'vacaciones') ? 'vacaciones' : 'permiso';
$estadoLabel = $esAprobado ? 'aprobada' : 'rechazada';
$colorEstado = $esAprobado ? '#16a34a' : '#dc2626';
$iconoEstado = $esAprobado ? '✅' : '❌';

$titulo    = "Tu solicitud de {$tipoLabel} ha sido {$estadoLabel}";
$preheader = "{$iconoEstado} Tu solicitud de {$tipoLabel} ({$fechas}) ha sido {$estadoLabel}.";

ob_start();
?>
<h1 style="margin:0 0 8px;font-size:26px;font-weight:700;color:#012133;font-family:Georgia,serif;line-height:1.3;">
  Hola, <?= htmlspecialchars($nombre) ?>
</h1>
<p style="margin:0 0 24px;font-size:15px;color:#7a7977;font-family:Georgia,serif;">
  Actualización sobre tu solicitud de <?= $tipoLabel ?>
</p>

<!-- Estado destacado -->
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:0 0 28px;">
  <tr>
    <td style="background-color:<?= $esAprobado ? '#f0fdf4' : '#fef2f2' ?>;border:1px solid <?= $esAprobado ? '#bbf7d0' : '#fecaca' ?>;border-radius:12px;padding:20px 24px;">
      <p style="margin:0 0 4px;font-size:18px;font-weight:700;color:<?= $colorEstado ?>;font-family:Georgia,serif;">
        <?= $iconoEstado ?> Solicitud <?= $estadoLabel ?>
      </p>
      <p style="margin:0;font-size:14px;color:#3d3c3b;font-family:Georgia,serif;">
        <?= ucfirst($tipoLabel) ?> · <?= htmlspecialchars($fechas) ?>
      </p>
    </td>
  </tr>
</table>

<?php if ($esAprobado): ?>
<p style="margin:0 0 20px;font-size:15px;color:#3d3c3b;line-height:1.7;font-family:Georgia,serif;">
  Tu solicitud ha sido aprobada. Recuerda coordinar con tu equipo para una transición fluida durante tu ausencia.
</p>
<?php else: ?>
<p style="margin:0 0 20px;font-size:15px;color:#3d3c3b;line-height:1.7;font-family:Georgia,serif;">
  Tu solicitud no ha podido ser aprobada en esta ocasión. Si tienes dudas, consulta con tu responsable directamente.
</p>
<?php endif; ?>

<!-- CTA -->
<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 32px;">
  <tr>
    <td style="border-radius:12px;background-color:#012133;">
      <a href="https://valirica.com/dashboard_empleado.php"
         style="display:inline-block;padding:14px 32px;font-size:15px;font-weight:700;color:#ffffff;text-decoration:none;font-family:Georgia,serif;letter-spacing:0.3px;">
        Ver mi perfil →
      </a>
    </td>
  </tr>
</table>

<hr style="border:none;border-top:1px solid #e8e6e3;margin:0 0 24px;" />

<p style="margin:0;font-size:13px;color:#9a9896;line-height:1.6;font-family:Georgia,serif;">
  ¿Alguna pregunta? Escríbenos a
  <a href="mailto:hola@valirica.com" style="color:#EF7F1B;text-decoration:none;">hola@valirica.com</a>
</p>
<?php
$contenido = ob_get_clean();
include __DIR__ . '/base.php';
