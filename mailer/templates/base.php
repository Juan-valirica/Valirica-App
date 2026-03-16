<?php
/**
 * base.php — Layout HTML base para todos los emails de Valírica.
 *
 * Variables esperadas:
 *   $titulo        string  Título del email (aparece en el preheader/tab)
 *   $preheader     string  Texto de previsualización en el cliente de email
 *   $contenido     string  HTML del cuerpo principal (ya renderizado)
 */
?>
<!DOCTYPE html>
<html lang="es" xmlns="http://www.w3.org/1999/xhtml">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <title><?= htmlspecialchars($titulo ?? 'Valírica') ?></title>
  <style>
    body, table, td, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
    table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
    img { -ms-interpolation-mode: bicubic; border: 0; height: auto; line-height: 100%; outline: none; text-decoration: none; }
    body { margin: 0 !important; padding: 0 !important; background-color: #f0eeeb; }
    a[x-apple-data-detectors] { color: inherit !important; text-decoration: none !important; }
    @media only screen and (max-width: 600px) {
      .wrapper { width: 100% !important; }
      .card    { border-radius: 16px !important; padding: 32px 24px !important; }
    }
  </style>
</head>
<body style="margin:0;padding:0;background-color:#f0eeeb;font-family:Georgia,serif;">

  <!-- Preheader invisible -->
  <div style="display:none;font-size:1px;color:#f0eeeb;line-height:1px;max-height:0px;max-width:0px;opacity:0;overflow:hidden;">
    <?= htmlspecialchars($preheader ?? '') ?>
  </div>

  <!-- Wrapper -->
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f0eeeb;">
    <tr>
      <td align="center" style="padding:40px 16px;">

        <!-- Card -->
        <table role="presentation" class="card wrapper" width="600" cellpadding="0" cellspacing="0" border="0"
               style="max-width:600px;width:100%;background:#ffffff;border-radius:20px;box-shadow:0 8px 40px rgba(1,33,51,.09);border:1px solid #e8e6e3;overflow:hidden;">

          <!-- Header con logo -->
          <tr>
            <td align="center" style="background-color:#012133;padding:32px 40px;">
              <img src="https://valirica.com/uploads/logo-valirica.png"
                   alt="Valírica" width="140" height="auto"
                   style="display:block;max-width:140px;" />
            </td>
          </tr>

          <!-- Contenido -->
          <tr>
            <td class="card" style="padding:40px 48px;">
              <?= $contenido ?>
            </td>
          </tr>

          <!-- Footer -->
          <tr>
            <td style="background-color:#f7f6f4;padding:24px 48px;border-top:1px solid #e8e6e3;">
              <p style="margin:0;font-size:12px;color:#9a9896;text-align:center;line-height:1.6;">
                © <?= date('Y') ?> Valírica · Este es un email automático, por favor no respondas a este mensaje.<br />
                Si tienes dudas escríbenos a
                <a href="mailto:hola@valirica.com" style="color:#EF7F1B;text-decoration:none;">hola@valirica.com</a>
              </p>
            </td>
          </tr>

        </table>
        <!-- /Card -->

      </td>
    </tr>
  </table>
  <!-- /Wrapper -->

</body>
</html>
