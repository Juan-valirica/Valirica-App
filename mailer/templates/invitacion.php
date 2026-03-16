<?php
$titulo    = 'Te han invitado a Valírica';
$preheader = htmlspecialchars($empresa_nombre) . ' quiere que te unas a su equipo en Valírica.';

ob_start();
?>
<h1 style="margin:0 0 8px;font-size:26px;font-weight:700;color:#012133;font-family:Georgia,serif;line-height:1.3;">
  Tienes una invitación 🎉
</h1>
<p style="margin:0 0 24px;font-size:15px;color:#7a7977;font-family:Georgia,serif;">
  <?= htmlspecialchars($empresa_nombre) ?> te ha invitado a unirte a su equipo.
</p>

<p style="margin:0 0 20px;font-size:15px;color:#3d3c3b;line-height:1.7;font-family:Georgia,serif;">
  <strong><?= htmlspecialchars($empresa_nombre) ?></strong> usa Valírica para gestionar el talento
  y la cultura de su equipo. Han pensado en ti para formar parte de este proceso.
</p>

<p style="margin:0 0 32px;font-size:15px;color:#3d3c3b;line-height:1.7;font-family:Georgia,serif;">
  Haz clic en el botón para crear tu cuenta. El enlace es válido durante <strong>7 días</strong>.
</p>

<!-- CTA -->
<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 32px;">
  <tr>
    <td style="border-radius:12px;background-color:#EF7F1B;">
      <a href="<?= htmlspecialchars($invite_url) ?>"
         style="display:inline-block;padding:14px 32px;font-size:15px;font-weight:700;color:#ffffff;text-decoration:none;font-family:Georgia,serif;letter-spacing:0.3px;">
        Aceptar invitación →
      </a>
    </td>
  </tr>
</table>

<p style="margin:0 0 8px;font-size:13px;color:#9a9896;font-family:Georgia,serif;">
  Si el botón no funciona, copia y pega este enlace en tu navegador:
</p>
<p style="margin:0 0 24px;font-size:12px;color:#EF7F1B;word-break:break-all;font-family:monospace;">
  <?= htmlspecialchars($invite_url) ?>
</p>

<hr style="border:none;border-top:1px solid #e8e6e3;margin:0 0 24px;" />

<p style="margin:0;font-size:13px;color:#9a9896;line-height:1.6;font-family:Georgia,serif;">
  Si no esperabas esta invitación, puedes ignorar este correo de forma segura.
</p>
<?php
$contenido = ob_get_clean();
include __DIR__ . '/base.php';
