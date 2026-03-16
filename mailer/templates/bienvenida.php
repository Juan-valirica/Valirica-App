<?php
$titulo    = '¡Bienvenido/a a Valírica!';
$preheader = 'Tu cuenta está lista. Descubre el potencial de tu equipo con Valírica.';

ob_start();
?>
<h1 style="margin:0 0 8px;font-size:26px;font-weight:700;color:#012133;font-family:Georgia,serif;line-height:1.3;">
  ¡Hola, <?= htmlspecialchars($nombre) ?>! 👋
</h1>
<p style="margin:0 0 24px;font-size:15px;color:#7a7977;font-family:Georgia,serif;">
  Tu cuenta en Valírica está lista.
</p>

<p style="margin:0 0 20px;font-size:15px;color:#3d3c3b;line-height:1.7;font-family:Georgia,serif;">
  Estamos muy contentos de tenerte a bordo. Valírica te ayudará a comprender a tu equipo en profundidad —
  sus fortalezas, su cultura y su potencial — para que puedas tomar mejores decisiones como líder.
</p>

<p style="margin:0 0 32px;font-size:15px;color:#3d3c3b;line-height:1.7;font-family:Georgia,serif;">
  Tu próximo paso es completar el perfil de tu empresa y comenzar a invitar a tu equipo.
</p>

<!-- CTA -->
<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 32px;">
  <tr>
    <td style="border-radius:12px;background-color:#EF7F1B;">
      <a href="https://valirica.com/dashboard.php"
         style="display:inline-block;padding:14px 32px;font-size:15px;font-weight:700;color:#ffffff;text-decoration:none;font-family:Georgia,serif;letter-spacing:0.3px;">
        Ir a mi dashboard →
      </a>
    </td>
  </tr>
</table>

<hr style="border:none;border-top:1px solid #e8e6e3;margin:0 0 24px;" />

<p style="margin:0;font-size:13px;color:#9a9896;line-height:1.6;font-family:Georgia,serif;">
  Si no creaste esta cuenta, ignora este correo o escríbenos a
  <a href="mailto:hola@valirica.com" style="color:#EF7F1B;text-decoration:none;">hola@valirica.com</a>
</p>
<?php
$contenido = ob_get_clean();
include __DIR__ . '/base.php';
