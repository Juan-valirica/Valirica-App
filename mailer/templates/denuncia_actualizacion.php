<?php
/**
 * denuncia_actualizacion.php — Actualización de estado al denunciante no anónimo
 *
 * Variables: $nombre, $reference_code, $nuevo_estado, $estado_label, $track_url
 */

$titulo    = "Actualización de tu denuncia {$reference_code} — {$estado_label}";
$preheader = "El estado de tu denuncia ha cambiado a: {$estado_label}.";

// Mensaje y color por estado
$estado_config = [
    'en_tramite' => [
        'color'   => '#EF7F1B',
        'bg'      => '#FFF7ED',
        'border'  => '#FED7AA',
        'icon'    => '🔍',
        'mensaje' => 'Tu denuncia está siendo investigada. El responsable del canal está llevando a cabo las actuaciones necesarias. Serás informado/a cuando haya una resolución.',
    ],
    'resuelta' => [
        'color'   => '#065F46',
        'bg'      => '#D1FAE5',
        'border'  => '#A7F3D0',
        'icon'    => '✅',
        'mensaje' => 'Tu denuncia ha sido resuelta. El expediente ha concluido. Si tienes dudas sobre el resultado, puedes contactar con el responsable del canal a través de los canales oficiales de tu empresa.',
    ],
    'archivada' => [
        'color'   => '#6B7280',
        'bg'      => '#F3F4F6',
        'border'  => '#E5E7EB',
        'icon'    => '📁',
        'mensaje' => 'El expediente de tu denuncia ha sido archivado por el responsable del canal. Esto puede deberse a que se ha resuelto por otras vías o se ha determinado que no procede su instrucción.',
    ],
];

$cfg = $estado_config[$nuevo_estado] ?? [
    'color'   => '#012133',
    'bg'      => '#f7f6f4',
    'border'  => '#e8e6e3',
    'icon'    => '📋',
    'mensaje' => 'El estado de tu denuncia ha sido actualizado.',
];

ob_start();
?>
<h1 style="margin:0 0 8px;font-size:26px;font-weight:700;color:#012133;font-family:Georgia,serif;line-height:1.3;">
  Actualización de tu denuncia <?= $cfg['icon'] ?>
</h1>
<p style="margin:0 0 24px;font-size:15px;color:#7a7977;font-family:Georgia,serif;">
  Hola <?= htmlspecialchars($nombre) ?>, te informamos de un cambio en el estado de tu denuncia.
</p>

<!-- Código y nuevo estado -->
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:0 0 24px;">
  <tr>
    <td style="background-color:#f7f6f4;border:1px solid #e8e6e3;border-radius:12px;padding:20px 24px;">
      <p style="margin:0 0 4px;font-size:12px;color:#9a9896;font-family:Georgia,serif;text-transform:uppercase;letter-spacing:.8px;">
        Código de referencia
      </p>
      <p style="margin:0 0 14px;font-size:18px;font-weight:700;color:#012133;font-family:monospace;letter-spacing:2px;">
        <?= htmlspecialchars($reference_code) ?>
      </p>
      <p style="margin:0;font-size:14px;color:#3d3c3b;font-family:Georgia,serif;">
        Nuevo estado:
        <span style="display:inline-block;padding:3px 12px;border-radius:20px;font-weight:700;font-size:13px;background-color:<?= $cfg['bg'] ?>;color:<?= $cfg['color'] ?>;border:1px solid <?= $cfg['border'] ?>;">
          <?= htmlspecialchars($estado_label) ?>
        </span>
      </p>
    </td>
  </tr>
</table>

<!-- Descripción del estado -->
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:0 0 24px;">
  <tr>
    <td style="background-color:<?= $cfg['bg'] ?>;border:1px solid <?= $cfg['border'] ?>;border-radius:10px;padding:16px 18px;">
      <p style="margin:0;font-size:14px;color:<?= $cfg['color'] ?>;font-family:Georgia,serif;line-height:1.7;">
        <?= htmlspecialchars($cfg['mensaje']) ?>
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
        Ver estado de mi denuncia →
      </a>
    </td>
  </tr>
</table>

<hr style="border:none;border-top:1px solid #e8e6e3;margin:0 0 20px;" />

<p style="margin:0;font-size:12px;color:#9a9896;line-height:1.6;font-family:Georgia,serif;">
  Este mensaje es confidencial y ha sido generado automáticamente por el Canal de Denuncias.<br>
  No respondas a este email. Usa el enlace de seguimiento para consultar el estado.
</p>
<?php
$contenido = ob_get_clean();
include __DIR__ . '/base.php';
