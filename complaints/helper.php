<?php
/**
 * complaints/helper.php — Funciones de soporte compartidas del módulo de denuncias
 */

if (defined('COMPLAINTS_HELPER_LOADED')) return;
define('COMPLAINTS_HELPER_LOADED', true);

// ─────────────────────────────────────────────────────────────────────────────
// Generación de código de referencia: VLD-YYYY-XXXX
// ─────────────────────────────────────────────────────────────────────────────

function generate_reference_code(mysqli $conn, int $company_id): string
{
    $year     = date('Y');
    $attempts = 0;
    do {
        $random = strtoupper(substr(bin2hex(random_bytes(3)), 0, 4));
        $code   = "VLD-{$year}-{$random}";
        $stmt   = $conn->prepare("SELECT id FROM complaints WHERE reference_code = ? LIMIT 1");
        $stmt->bind_param("s", $code);
        $stmt->execute();
        $res    = stmt_get_result($stmt);
        $exists = ($res && $res->num_rows > 0);
        $stmt->close();
        $attempts++;
    } while ($exists && $attempts < 30);
    return $code;
}

// ─────────────────────────────────────────────────────────────────────────────
// Plazos legales
//   ES: días hábiles (lun–vie, sin festivos nacionales)
//   CO: días naturales
// ─────────────────────────────────────────────────────────────────────────────

function get_working_days_deadline(int $days, string $country): string
{
    if ($country === 'CO') {
        $date = new DateTime();
        $date->modify("+{$days} days");
        return $date->format('Y-m-d H:i:s');
    }

    // España — días hábiles (lun–vie)
    $date  = new DateTime();
    $added = 0;
    while ($added < $days) {
        $date->modify('+1 day');
        if ((int)$date->format('N') < 6) { // 1=lun … 5=vie
            $added++;
        }
    }
    return $date->format('Y-m-d H:i:s');
}

// ─────────────────────────────────────────────────────────────────────────────
// Slug público del canal — URL-safe, único, derivado del nombre de empresa
// Ejemplo: "Empresa Ejemplo S.L." → "empresa-ejemplo-sl"
// ─────────────────────────────────────────────────────────────────────────────

function generate_canal_slug(mysqli $conn, string $company_name): string
{
    // Normalizar: minúsculas, solo alfanumérico y guiones, máx 40 chars
    $base = mb_strtolower($company_name, 'UTF-8');
    // Transliterar acentos básicos
    $base = strtr($base, [
        'á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n',
        'à'=>'a','è'=>'e','ì'=>'i','ò'=>'o','ù'=>'u',
        'â'=>'a','ê'=>'e','î'=>'i','ô'=>'o','û'=>'u',
    ]);
    $base = preg_replace('/[^a-z0-9]+/', '-', $base);
    $base = trim($base, '-');
    $base = substr($base, 0, 40);

    if ($base === '') {
        $base = 'canal';
    }

    // Garantizar unicidad
    $slug = $base;
    $i    = 2;
    while (true) {
        $stmt = $conn->prepare(
            "SELECT company_id FROM complaint_channel_config WHERE canal_slug = ? LIMIT 1"
        );
        $stmt->bind_param("s", $slug);
        $stmt->execute();
        $res = stmt_get_result($stmt);
        $stmt->close();
        if (!$res || $res->num_rows === 0) {
            break; // slug libre
        }
        $slug = $base . '-' . $i;
        $i++;
        if ($i > 999) {
            $slug = $base . '-' . bin2hex(random_bytes(3));
            break;
        }
    }

    return $slug;
}

// ─────────────────────────────────────────────────────────────────────────────
// Configuración estática por país (plazos legales, tipos permitidos, textos)
//   ES → Ley 2/2023 · RGPD · LOPD-GDD
//   CO → Ley 1010/2006 · Ley 2365/2024 · Resolución 3461/2025
// ─────────────────────────────────────────────────────────────────────────────

function get_country_config(string $country): array
{
    if ($country === 'CO') {
        return [
            'receipt_days'      => 0,   // acuse inmediato (Colombia)
            'resolution_days'   => 65,
            'responsible_label' => 'Comité de Convivencia Laboral (CCL)',
            'allowed_types'     => [
                'acoso_laboral',
                'acoso_sexual',
                'discriminacion',
                'otro',
            ],
            'legal_text'        => 'Ley 1010/2006 · Ley 2365/2024 · Resolución 3461/2025',
        ];
    }

    // Por defecto: España
    return [
        'receipt_days'      => 7,   // días hábiles (ES)
        'resolution_days'   => 90,
        'responsible_label' => 'Responsable del Sistema',
        'allowed_types'     => [
            'acoso_laboral',
            'acoso_sexual',
            'fraude',
            'corrupcion',
            'discriminacion',
            'incumplimiento_normativo',
            'conflicto_interes',
            'otro',
        ],
        'legal_text'        => 'Ley 2/2023 · RGPD · LOPD-GDD',
    ];
}

// ─────────────────────────────────────────────────────────────────────────────
// Config del canal de una empresa
// ─────────────────────────────────────────────────────────────────────────────

function get_company_config(mysqli $conn, int $company_id): ?array
{
    $stmt = $conn->prepare("
        SELECT ccc.*, u.empresa AS company_name, u.country AS company_country
        FROM complaint_channel_config ccc
        INNER JOIN usuarios u ON u.id = ccc.company_id
        WHERE ccc.company_id = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $company_id);
    $stmt->execute();
    $res = stmt_get_result($stmt);
    $stmt->close();
    if (!$res || $res->num_rows === 0) return null;
    return $res->fetch_assoc();
}

// ─────────────────────────────────────────────────────────────────────────────
// Trazabilidad de actividad
// ─────────────────────────────────────────────────────────────────────────────

function log_complaint_activity(
    mysqli $conn,
    int    $complaint_id,
    string $actor_tipo,
    ?int   $actor_id,
    string $action,
    ?string $notes = null
): void {
    $stmt = $conn->prepare("
        INSERT INTO complaint_activities (complaint_id, actor_tipo, actor_id, action, notes)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("isiss", $complaint_id, $actor_tipo, $actor_id, $action, $notes);
    $stmt->execute();
    $stmt->close();
}

// ─────────────────────────────────────────────────────────────────────────────
// Notificación interna (tabla notificaciones)
// ─────────────────────────────────────────────────────────────────────────────

function send_internal_notification(
    mysqli $conn,
    int    $usuario_id,
    string $tipo,
    string $titulo,
    string $mensaje,
    int    $ref_id
): void {
    $tipo_destino    = 'empresa';
    $referencia_tipo = 'denuncia';
    $stmt = $conn->prepare("
        INSERT INTO notificaciones
            (usuario_destino_id, tipo_destino, tipo, titulo, mensaje, referencia_tipo, referencia_id)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param(
        "isssssi",
        $usuario_id, $tipo_destino, $tipo, $titulo, $mensaje, $referencia_tipo, $ref_id
    );
    $stmt->execute();
    $stmt->close();
}

// ─────────────────────────────────────────────────────────────────────────────
// Días restantes hasta deadline (para urgencia visual)
// ─────────────────────────────────────────────────────────────────────────────

function days_until(string $deadline): int
{
    $now  = new DateTime();
    $end  = new DateTime($deadline);
    $diff = $now->diff($end);
    return $diff->invert ? -$diff->days : $diff->days;
}

// ─────────────────────────────────────────────────────────────────────────────
// Label legible del tipo de denuncia
// ─────────────────────────────────────────────────────────────────────────────

function complaint_type_label(string $type): string
{
    $map = [
        'acoso_laboral'          => 'Acoso laboral',
        'acoso_sexual'           => 'Acoso sexual',
        'fraude'                 => 'Fraude',
        'corrupcion'             => 'Corrupción',
        'discriminacion'         => 'Discriminación',
        'incumplimiento_normativo' => 'Incumplimiento normativo',
        'conflicto_interes'      => 'Conflicto de interés',
        'otro'                   => 'Otro',
    ];
    return $map[$type] ?? ucfirst(str_replace('_', ' ', $type));
}

// ─────────────────────────────────────────────────────────────────────────────
// Color chip de urgencia según días restantes
// ─────────────────────────────────────────────────────────────────────────────

function urgency_chip(int $days_left): string
{
    if ($days_left < 0)  return '<span class="chip chip-vencida">Vencida</span>';
    if ($days_left <= 3) return '<span class="chip chip-critica">≤3 días</span>';
    if ($days_left <= 10) return '<span class="chip chip-alta">' . $days_left . ' días</span>';
    return '<span class="chip chip-ok">' . $days_left . ' días</span>';
}
