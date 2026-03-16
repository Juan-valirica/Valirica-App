<?php
/**
 * Mailer — Servicio central de emails para Valírica
 * Usa Amazon SES vía SMTP con PHPMailer.
 *
 * Uso:
 *   require_once __DIR__ . '/../vendor/autoload.php';
 *   $ok = Mailer::sendBienvenida($nombre, $email);
 *   $ok = Mailer::sendInvitacion($emailDestino, $inviteUrl, $empresaNombre);
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

class Mailer
{
    // -------------------------------------------------------------------------
    // Método base: configura y devuelve un PHPMailer listo para enviar
    // -------------------------------------------------------------------------
    private static function build(): PHPMailer
    {
        $mail = new PHPMailer(true);

        $mail->isSMTP();
        $mail->Host       = SES_SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SES_SMTP_USER;
        $mail->Password   = SES_SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SES_SMTP_PORT;
        $mail->CharSet    = 'UTF-8';
        $mail->Encoding   = 'base64';

        // Configuration Set de SES para métricas (opens, clicks, bounces)
        $mail->addCustomHeader('X-SES-CONFIGURATION-SET', SES_CONFIG_SET);

        $mail->setFrom(SES_FROM_EMAIL, SES_FROM_NAME);
        $mail->addReplyTo(SES_REPLY_TO, SES_FROM_NAME);

        $mail->isHTML(true);

        return $mail;
    }

    // -------------------------------------------------------------------------
    // Renderiza un template PHP pasándole variables
    // -------------------------------------------------------------------------
    private static function renderTemplate(string $template, array $vars = []): string
    {
        $path = __DIR__ . '/templates/' . $template . '.php';
        if (!file_exists($path)) {
            error_log("Mailer: template no encontrado: $path");
            return '';
        }
        extract($vars, EXTR_SKIP);
        ob_start();
        include $path;
        return ob_get_clean();
    }

    // -------------------------------------------------------------------------
    // Envío genérico — método interno
    // -------------------------------------------------------------------------
    private static function send(string $toEmail, string $toName, string $subject, string $htmlBody): bool
    {
        try {
            $mail = self::build();
            $mail->addAddress($toEmail, $toName);
            $mail->Subject = $subject;
            $mail->Body    = $htmlBody;
            $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>', '</li>'], "\n", $htmlBody));
            $mail->send();
            return true;
        } catch (PHPMailerException $e) {
            error_log("Mailer error [{$toEmail}]: " . $e->getMessage());
            return false;
        } catch (\Throwable $e) {
            error_log("Mailer unexpected error [{$toEmail}]: " . $e->getMessage());
            return false;
        }
    }

    // =========================================================================
    // EMAILS PÚBLICOS
    // =========================================================================

    /**
     * Email de bienvenida al registrarse en Valírica.
     */
    public static function sendBienvenida(string $nombre, string $email): bool
    {
        $html = self::renderTemplate('bienvenida', [
            'nombre' => $nombre,
            'email'  => $email,
        ]);
        return self::send($email, $nombre, '¡Bienvenido/a a Valírica, ' . $nombre . '!', $html);
    }

    /**
     * Email de invitación para un nuevo miembro del equipo.
     *
     * @param string $emailDestino  Correo del invitado
     * @param string $inviteUrl     URL completa con token de invitación
     * @param string $empresaNombre Nombre de la empresa que invita
     */
    public static function sendInvitacion(string $emailDestino, string $inviteUrl, string $empresaNombre): bool
    {
        $html = self::renderTemplate('invitacion', [
            'empresa_nombre' => $empresaNombre,
            'invite_url'     => $inviteUrl,
        ]);
        return self::send(
            $emailDestino,
            '',
            $empresaNombre . ' te ha invitado a unirte a Valírica',
            $html
        );
    }

    /**
     * Notificación al empleado cuando se aprueba o rechaza su solicitud.
     *
     * @param string $nombre     Nombre del empleado
     * @param string $email      Correo del empleado
     * @param string $tipo       'permiso' | 'vacaciones'
     * @param string $estado     'aprobado' | 'rechazado'
     * @param string $fechas     Texto con las fechas (ej. "3 al 7 de marzo")
     */
    public static function sendAprobacion(
        string $nombre,
        string $email,
        string $tipo,
        string $estado,
        string $fechas
    ): bool {
        $html = self::renderTemplate('aprobacion', [
            'nombre' => $nombre,
            'tipo'   => $tipo,
            'estado' => $estado,
            'fechas' => $fechas,
        ]);

        $tipoLabel  = $tipo === 'vacaciones' ? 'vacaciones' : 'permiso';
        $estadoLabel = $estado === 'aprobado' ? 'aprobada ✅' : 'rechazada';
        $asunto = "Tu solicitud de {$tipoLabel} ha sido {$estadoLabel}";

        return self::send($email, $nombre, $asunto, $html);
    }

    /**
     * Alerta al empleador cuando un empleado hace una solicitud nueva.
     *
     * @param string $emailEmpleador  Correo del empleador/responsable
     * @param string $nombreEmpleador Nombre del empleador
     * @param string $nombreEmpleado  Nombre del empleado que solicita
     * @param string $tipo            'permiso' | 'vacaciones'
     * @param string $fechas          Texto con las fechas
     * @param string $dashboardUrl    URL al dashboard del empleador
     */
    public static function sendNuevaSolicitud(
        string $emailEmpleador,
        string $nombreEmpleador,
        string $nombreEmpleado,
        string $tipo,
        string $fechas,
        string $dashboardUrl
    ): bool {
        $html = self::renderTemplate('nueva_solicitud', [
            'nombre_empleador' => $nombreEmpleador,
            'nombre_empleado'  => $nombreEmpleado,
            'tipo'             => $tipo,
            'fechas'           => $fechas,
            'dashboard_url'    => $dashboardUrl,
        ]);

        $tipoLabel = $tipo === 'vacaciones' ? 'vacaciones' : 'permiso';
        $asunto = "{$nombreEmpleado} ha solicitado {$tipoLabel}";

        return self::send($emailEmpleador, $nombreEmpleador, $asunto, $html);
    }
}
