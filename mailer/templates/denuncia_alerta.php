<?php
/**
 * denuncia_alerta.php — Alerta de vencimiento inminente al responsable
 *
 * Variables: $nombre_responsable, $reference_code, $dias_restantes, $manage_url
 */

if ($dias_restantes <= 0) {
    $titulo    = "🚨 VENCIDA — Denuncia {$reference_code}";
    $preheader = "El plazo de resolución de la denuncia {$reference_code} ha vencido.";
    $urgencia_color  = '#B91C1C';
    $urgencia_bg     = '#FEE2E2';
    $urgencia_border = '#FECACA';
    $urgencia_icon   = '🚨';
    $urgencia_titulo = 'Plazo vencido';
    $urgencia_msg    = "El plazo de resolución de esta denuncia <strong>ha vencido</strong>. Es imprescindible que actúes de inmediato para evitar incumplimientos normativos.";
} elseif ($dias_restantes <= 1) {
    $titulo    = "⚠️ Vence mañana — Denuncia {$reference_code}";
    $preheader = "La denuncia {$reference_code} vence mañana. Actúa de inmediato.";
    $urgencia_color  = '#B91C1C';
    $urgencia_bg     = '#FEF2F2';
    $urgencia_border = '#FECACA';
    $urgencia_icon   = '⚠️';
    $urgencia_titulo = 'Vence mañana';
    $urgencia_msg    = "Esta denuncia vence <strong>mañana</strong>. Debes resolverla hoy para cumplir con los plazos legales.";
} elseif ($dias_restantes <= 3) {
    $titulo    = "⚠️ Vence en {$dias_restantes} días — Denuncia {$reference_code}";
    $preheader = "La denuncia {$reference_code} vence en {$dias_restantes} días.";
    $urgencia_color  = '#92400E';
    $urgencia_bg     = '#FEF3C7';
    $urgencia_border = '#FDE68A';
    $urgencia_icon   = '⏰';
    $urgencia_titulo = "Vence en {$dias_restantes} días";
    $urgencia_msg    = "El plazo de resolución de esta denuncia expira en <strong>{$dias_restantes} días</strong>. Revisa el expediente y toma las acciones necesarias.";
} else {
    $titulo    = "Recordatorio — Denuncia {$reference_code} vence en {$dias_restantes} días";
    $preheader = "Recordatorio: la denuncia {$reference_code} vence en {$dias_restantes} días.";
    $urgencia_color  = '#1D4ED8';
    $urgencia_bg     = '#DBEAFE';
    $urgencia_border = '#BFDBFE';
    $urgencia_icon   = '📅';
    $urgencia_titulo = "Vence en {$dias_restantes} días";
    $urgencia_msg    = "Te recordamos que el plazo de resolución de esta denuncia expira en <strong>{$dias_restantes} días</strong>.";
}

ob_start();
?>
<h1 style="margin:0 0 8px;font-size:26px;font-weight:700;color:#012133;font-family:Georgia,serif;line-height:1.3;">
  <?= $urgencia_icon ?> Alerta de vencimiento
</h1>
<p style="margin:0 0 24px;font-size:15px;color:#7a7977;font-family:Georgia,serif;">
  Hola <?= htmlspecialchars($nombre_responsable) ?>, tienes una denuncia con plazo próximo a vencer.
</p>

<!-- Alerta urgencia -->
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:0 0 24px;">
  <tr>
    <td style="background-color:<?= $urgencia_bg ?>;border:2px solid <?= $urgencia_border ?>;border-radius:12px;padding:20px 24px;">
      <p style="margin:0 0 6px;font-size:16px;font-weight:700;color:<?= $urgencia_color ?>;font-family:Georgia,serif;">
        <?= $urgencia_titulo ?>
      </p>
      <p style="margin:0;font-size:14px;color:<?= $urgencia_color ?>;font-family:Georgia,serif;line-height:1.6;">
        <?= $urgencia_msg ?>
      </p>
    </td>
  </tr>
</table>

<!-- Código referencia -->
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:0 0 24px;">
  <tr>
    <td style="background-color:#f7f6f4;border:1px solid #e8e6e3;border-radius:12px;padding:20px 24px;">
      <p style="margin:0 0 6px;font-size:12px;color:#9a9896;font-family:Georgia,serif;text-transform:uppercase;letter-spacing:.8px;">
        Denuncia afectada
      </p>
      <p style="margin:0;font-size:22px;font-weight:700;color:#012133;font-family:monospace;letter-spacing:2px;">
        <?= htmlspecialchars($reference_code) ?>
      </p>
    </td>
  </tr>
</table>

<!-- Aviso legal -->
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:0 0 24px;">
  <tr>
    <td style="background-color:#FFF7ED;border:1px solid #FED7AA;border-radius:10px;padding:14px 18px;">
      <p style="margin:0;font-size:13px;color:#92400E;font-family:Georgia,serif;line-height:1.6;">
        ⚖️ El incumplimiento de los plazos legales del canal de denuncias puede acarrear
        <strong>sanciones administrativas y responsabilidad legal</strong> para la empresa.
        Actúa con urgencia.
      </p>
    </td>
  </tr>
</table>

<!-- CTA -->
<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 32px;">
  <tr>
    <td style="border-radius:12px;background-color:#EF7F1B;">
      <a href="<?= htmlspecialchars($manage_url) ?>"
         style="display:inline-block;padding:14px 32px;font-size:15px;font-weight:700;color:#ffffff;text-decoration:none;font-family:Georgia,serif;letter-spacing:0.3px;">
        Gestionar denuncia urgente →
      </a>
    </td>
  </tr>
</table>

<hr style="border:none;border-top:1px solid #e8e6e3;margin:0 0 20px;" />

<p style="margin:0;font-size:12px;color:#9a9896;line-height:1.6;font-family:Georgia,serif;">
  Este aviso ha sido generado automáticamente por el sistema de alertas del Canal de Denuncias de Valírica.<br>
  Es confidencial y está dirigido exclusivamente al responsable designado del canal.
</p>
<?php
$contenido = ob_get_clean();
include __DIR__ . '/base.php';
