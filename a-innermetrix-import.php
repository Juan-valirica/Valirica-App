<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

/* ==============================================
   Importador Innermetrix — Valírica (MAESTRO)
   - Acceso EXCLUSIVO: vale@valirica.com
   - Selector carga TODAS las empresas de la tabla `usuarios`
   - Sube CSV y mapea por email → equipo.correo
   - Guarda crudo en imx_raw y upserts en imx_*
   ============================================== */

session_start();
require 'config.php'; // Debe exponer $conn = new mysqli(...)

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// 1) Autenticación de sesión
if (!isset($_SESSION['user_id'])) {
    // Si no hay usuario logueado, te mando al login
    header("Location: login.php");
    exit;
}

$logged_user_id = (int)$_SESSION['user_id'];

// 2) Verificación del rol del usuario logueado
$allowed = false;

try {
    $stmtRole = $conn->prepare("SELECT rol FROM usuarios WHERE id = ?");
    $stmtRole->bind_param("i", $logged_user_id);
    $stmtRole->execute();
    $resR = stmt_get_result($stmtRole);
    $userRow = $resR->fetch_assoc();
    $stmtRole->close();

    $logged_role = strtolower(trim($userRow['rol'] ?? ''));

    // Permitir acceso SOLO a providers
    if ($logged_role === 'provider') {
        $allowed = true;
    }

} catch (Throwable $e) {
    $allowed = false;
}

if (!$allowed) {
    http_response_code(403);
    echo "Acceso restringido. Esta página solo está disponible para usuarios con rol PROVIDER.";
    exit;
}





// 3) Cargar SOLO las marcas/empresas vinculadas al provider logueado
//    - Incluye al propio provider
//    - Incluye solo empresas cuyo provider_id = id del provider
$brands = [];

$stmtBrands = $conn->prepare("
    SELECT id, empresa, logo
    FROM usuarios
    WHERE 
        (
            id = ? 
            AND LOWER(rol) = 'provider'
        )
        OR
        (
            provider_id = ?
            AND empresa IS NOT NULL
        )
    ORDER BY empresa ASC
");

$stmtBrands->bind_param("ii", $logged_user_id, $logged_user_id);
$stmtBrands->execute();
$resb = stmt_get_result($stmtBrands);

while ($row = $resb->fetch_assoc()) {
    $brands[] = $row;
}

$stmtBrands->close();


// 4) Variables de resultado
$feedback = null;  // mensaje de éxito/error
$report   = [];    // desglose de importación
$errors   = [];    // errores por fila u operativos
// Variables de UI/estado seguras para el render aunque no haya POST
$all_competencias = [];
$comp_set = [];
$importedEquipos = [];
$importedEmails  = [];
$selectedEquipoId = 0;
$selectedEmail = '';
$existing_config = [];
// Configuración de competencias ya existente para la empresa
$empresa_has_config      = false;   // true si la empresa ya parametrizó categorías
$empresa_config_template = [];      // plantilla: competencia => valor





/* ====== Helpers para mapear nombres bonitos → códigos CSV (ai_combo_*) ====== */
if (!function_exists('to_code')) {
  function to_code($name){
    $s = trim(mb_strtolower((string)$name, 'UTF-8'));
    $s = strtr($s, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n']);
    $s = preg_replace('~[^a-z0-9]+~u','_', $s);
    $s = trim($s, '_');
    return 'ai_combo_'.$s;
  }
}

/* ====== CATEGORÍAS → COMPETENCIAS (mapa) ======
   IMPORTANTE:
   - Si tu CSV trae columnas con prefijo ai_combo_ y nombres normalizados (sin acentos, con _),
     puedes definir las competencias usando to_code([...]).
   - Si tus columnas tienen códigos diferentes, REEMPLAZA los to_code([...]) por los códigos exactos.
*/


/* (Opcional) Seguridad: si por algún motivo el mapa está vacío, define uno seguro para evitar errores. */
if (empty($CATEGORIAS_MAP) || !is_array($CATEGORIAS_MAP)) {
  $CATEGORIAS_MAP = [];
}





// 5) Procesamiento del POST (importación)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['empresa_id'])) {
    $empresa_id = (int)($_POST['empresa_id'] ?? 0);
    $dry_run    = isset($_POST['dry_run']) ? (bool)$_POST['dry_run'] : false;

    if ($empresa_id <= 0) {
        $errors[] = "Debes seleccionar una marca (empresa).";
    }

    if (empty($_FILES['imx_csv']['tmp_name'])) {
        $errors[] = "Debes seleccionar un archivo CSV de Innermetrix.";
    }

    if (!$errors) {

        // === 5.1 Cargar PLANTILLA de competencias ya configuradas para esta empresa ===
        // Si la empresa ya tiene imx_config, usamos esa configuración como plantilla
        $sqlCfg = "
          SELECT 
            c.competencia,
            MAX(c.valor) AS valor
          FROM imx_config c
          JOIN equipo e ON e.id = c.equipo_id
          WHERE e.usuario_id = ?
          GROUP BY c.competencia
        ";
        $stCfg = $conn->prepare($sqlCfg);
        $stCfg->bind_param("i", $empresa_id);
        $stCfg->execute();
        $rsCfg = stmt_get_result($stCfg);
        while ($rowCfg = $rsCfg->fetch_assoc()) {
            $empresa_has_config = true;
            $empresa_config_template[$rowCfg['competencia']] = (int)$rowCfg['valor'];
        }
        $stCfg->close();

        try {
            // Validación mínima de tamaño (opcional, evita CSVs absurdamente grandes)
            if (filesize($_FILES['imx_csv']['tmp_name']) > 25 * 1024 * 1024) {
                throw new RuntimeException("El CSV supera el límite de 25 MB.");
            }

            $csv = file_get_contents($_FILES['imx_csv']['tmp_name']);

            // Normaliza encoding
            $csv = mb_convert_encoding(
                $csv,
                'UTF-8',
                mb_detect_encoding($csv, 'UTF-8, ISO-8859-1, Windows-1252', true) ?: 'UTF-8'
            );

            // Detecta delimitador
            $delim = (substr_count($csv, ';') > substr_count($csv, ',')) ? ';' : ',';

            // Tokeniza
            $lines = preg_split("/\r\n|\n|\r/", $csv);
            if (!$lines || count($lines) < 2) {
                $errors[] = "El CSV parece vacío o no tiene encabezado.";
            } else {
                $header = array_map('trim', str_getcsv(array_shift($lines), $delim));
                $idx    = array_flip($header);

                // Columnas mínimas requeridas
                $required = [
                    'email',
                    'disc_d_auth','disc_i_auth','disc_s_auth','disc_c_auth',
                    'disc_d_mod','disc_i_mod','disc_s_mod','disc_c_mod',
                    'values_aes','values_eco','values_ind','values_pol','values_alt','values_reg','values_the',
                    'ai_dimbal_empathy_score','ai_dimbal_empathy_sign',
                    'ai_dimbal_practical_thinking_score','ai_dimbal_practical_thinking_sign',
                    'ai_dimbal_systems_judgment_score','ai_dimbal_systems_judgment_sign',
                    'ai_dimbal_self_esteem_score','ai_dimbal_self_esteem_sign',
                    'ai_dimbal_role_awareness_score','ai_dimbal_role_awareness_sign',
                    'ai_dimbal_self_direction_score','ai_dimbal_self_direction_sign'
                ];
                foreach ($required as $c) {
                    if (!isset($idx[$c])) {
                        $errors[] = "Falta la columna requerida en el CSV: <strong>{$c}</strong>";
                    }
                }

                if (!$errors) {
                    // Previsualización
                    $preview_total   = 0;
                    $preview_match   = 0;
                    $preview_nomatch = 0;

                    // Catálogo acumulado de competencias y usuarios procesados
                    $all_competencias = [];     // lista final (strings ai_combo_*)
                    $comp_set         = [];     // set para no duplicar (clave => true)
                    $importedEquipos  = [];     // email => equipo_id (para selector)
                    $importedEmails   = [];     // equipo_id => email (reverse)

                    $not_found   = []; // correos sin match
                    $imported    = 0;
                    $skipped     = 0;
                    $competencies = 0;

                    // Buscar equipo por (usuario_id, correo)
                    $stmtFind = $conn->prepare("SELECT id FROM equipo WHERE usuario_id = ? AND LOWER(correo) = LOWER(?)");

                    // Si no es dry-run, abrir transacción y preparar upserts
                    $stmtRaw     = null;
                    $stmtDisc    = null;
                    $stmtVal     = null;
                    $stmtThink   = null;
                    $stmtC       = null;
                    $stmtCfgCopy = null;

                    if (!$dry_run) {
                        $conn->begin_transaction();

                        // Crudo
                        $stmtRaw = $conn->prepare("
                          INSERT INTO imx_raw (usuario_id, correo, payload_json)
                          VALUES (?,?,?)
                        ");

                        // DISC
                        $stmtDisc = $conn->prepare("
                          INSERT INTO imx_disc (equipo_id,d_auth,i_auth,s_auth,c_auth,d_mod,i_mod,s_mod,c_mod)
                          VALUES (?,?,?,?,?,?,?,?,?)
                          ON DUPLICATE KEY UPDATE
                            d_auth=VALUES(d_auth), i_auth=VALUES(i_auth), s_auth=VALUES(s_auth), c_auth=VALUES(c_auth),
                            d_mod=VALUES(d_mod),   i_mod=VALUES(i_mod),   s_mod=VALUES(s_mod),   c_mod=VALUES(c_mod)
                        ");

                        // VALUES
                        $stmtVal = $conn->prepare("
                          INSERT INTO imx_values (equipo_id,aes,eco,ind,pol,alt,reg,the)
                          VALUES (?,?,?,?,?,?,?,?)
                          ON DUPLICATE KEY UPDATE
                            aes=VALUES(aes), eco=VALUES(eco), ind=VALUES(ind), pol=VALUES(pol),
                            alt=VALUES(alt), reg=VALUES(reg), the=VALUES(the)
                        ");

                        // THINKING
                        $stmtThink = $conn->prepare("
                          INSERT INTO imx_thinking (
                            equipo_id, empathy_score, empathy_sign, practical_score, practical_sign,
                            systems_score, systems_sign, self_esteem_score, self_esteem_sign,
                            role_awareness_score, role_awareness_sign, self_direction_score, self_direction_sign
                          ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)
                          ON DUPLICATE KEY UPDATE
                            empathy_score=VALUES(empathy_score), empathy_sign=VALUES(empathy_sign),
                            practical_score=VALUES(practical_score), practical_sign=VALUES(practical_sign),
                            systems_score=VALUES(systems_score), systems_sign=VALUES(systems_sign),
                            self_esteem_score=VALUES(self_esteem_score), self_esteem_sign=VALUES(self_esteem_sign),
                            role_awareness_score=VALUES(role_awareness_score), role_awareness_sign=VALUES(role_awareness_sign),
                            self_direction_score=VALUES(self_direction_score), self_direction_sign=VALUES(self_direction_sign)
                        ");

                        // COMPETENCIAS individuales (ai_combo_*)
                        $stmtC = $conn->prepare("
                          INSERT INTO imx_competency (equipo_id, code, score)
                          VALUES (?,?,?)
                          ON DUPLICATE KEY UPDATE score=VALUES(score)
                        ");

                        // Copiar PLANTILLA de imx_config (competencia base → nuevos equipos)
                        if (!empty($empresa_config_template)) {
                            $stmtCfgCopy = $conn->prepare("
                              INSERT INTO imx_config (equipo_id, competencia, valor, created_at, updated_at)
                              VALUES (?,?,?, NOW(), NOW())
                              ON DUPLICATE KEY UPDATE valor = VALUES(valor), updated_at = VALUES(updated_at)
                            ");
                        }
                    }

                    // Helpers
                    $toI = fn($x)=>max(0,min(100,(int)$x));
                    $toF = fn($x)=>is_numeric($x)?max(0,min(10,(float)$x)):null;
                    $sgn = fn($s)=>in_array($s,['+','-','0'],true)?$s: (is_numeric($s)?((float)$s>=0?'+':'-'):'0');

                    foreach ($lines as $lineNo => $line) {
                        if (trim($line) === '') continue;

                       $cols = str_getcsv($line, $delim);
$colCount    = count($cols);
$headerCount = count($header);

// Si la fila tiene MENOS columnas que el header → la saltamos y avisamos
if ($colCount < $headerCount) {
    $skipped++;
    if ($skipped <= 5) {
        // Mensaje de ayuda para que veas el problema en la caja de "Errores"
        $errors[] = "La fila " . ($lineNo + 2) . " tiene solo {$colCount} columnas; el encabezado tiene {$headerCount}. Revisa el delimitador o columnas vacías.";
    }
    continue;
}

// Si la fila tiene MÁS columnas que el header (típico por un separador al final),
// recortamos las columnas sobrantes para que array_combine funcione.
if ($colCount > $headerCount) {
    $cols = array_slice($cols, 0, $headerCount);
    $colCount = $headerCount;
}

$row = array_combine($header, $cols);
$correo_csv = trim($row['email'] ?? '');
if ($correo_csv === '') {
    $skipped++;
    continue;
}



                        if ($correo_csv === '') {
                            $skipped++;
                            continue;
                        }

                        $preview_total++;

                        // Match por (empresa seleccionada, correo)
                        $stmtFind->bind_param("is", $empresa_id, $correo_csv);
                        $stmtFind->execute();
                        $resF = stmt_get_result($stmtFind);
                        $equipo = $resF->fetch_assoc();
                        $equipo_id = (int)($equipo['id'] ?? 0);

                        if ($equipo_id <= 0) {
                            $preview_nomatch++;
                            if (count($not_found) < 25) $not_found[] = $correo_csv;
                            continue;
                        } else {
                            $preview_match++;
                        }

                        // Registrar usuario/equipo procesado para la UI posterior
                        if (!isset($importedEquipos[$correo_csv])) {
                            $importedEquipos[$correo_csv] = $equipo_id;
                            $importedEmails[$equipo_id]   = $correo_csv;
                        }

                        // Acumular catálogo de competencias ai_combo_* de esta fila (sin duplicados)
                        foreach ($row as $k2 => $v2) {
                            if (strpos($k2, 'ai_combo_') === 0) {
                                if (!isset($comp_set[$k2])) {
                                    $comp_set[$k2] = true;
                                    $all_competencias[] = $k2;
                                }
                            }
                        }

                        // Si es dry-run, no escribimos en BD
                        if ($dry_run) {
                            continue;
                        }

                        // ====== ESCRITURAS EN BD ======

                        // Guarda crudo
                        $payload = json_encode($row, JSON_UNESCAPED_UNICODE);
                        $stmtRaw->bind_param("iss", $empresa_id, $correo_csv, $payload);
                        $stmtRaw->execute();

                        // DISC
                        $disc_d_auth = $toI($row['disc_d_auth']);
                        $disc_i_auth = $toI($row['disc_i_auth']);
                        $disc_s_auth = $toI($row['disc_s_auth']);
                        $disc_c_auth = $toI($row['disc_c_auth']);
                        $disc_d_mod  = $toI($row['disc_d_mod']);
                        $disc_i_mod  = $toI($row['disc_i_mod']);
                        $disc_s_mod  = $toI($row['disc_s_mod']);
                        $disc_c_mod  = $toI($row['disc_c_mod']);

                        $stmtDisc->bind_param(
                          "iiiiiiiii",
                          $equipo_id,
                          $disc_d_auth, $disc_i_auth, $disc_s_auth, $disc_c_auth,
                          $disc_d_mod,  $disc_i_mod,  $disc_s_mod,  $disc_c_mod
                        );
                        $stmtDisc->execute();

                        // VALUES
                        $val_aes = $toI($row['values_aes']);
                        $val_eco = $toI($row['values_eco']);
                        $val_ind = $toI($row['values_ind']);
                        $val_pol = $toI($row['values_pol']);
                        $val_alt = $toI($row['values_alt']);
                        $val_reg = $toI($row['values_reg']);
                        $val_the = $toI($row['values_the']);

                        $stmtVal->bind_param(
                          "iiiiiiii",
                          $equipo_id,
                          $val_aes, $val_eco, $val_ind,
                          $val_pol, $val_alt, $val_reg, $val_the
                        );
                        $stmtVal->execute();

                        // THINKING
                        $th_empathy_score    = $toF($row['ai_dimbal_empathy_score']);
                        $th_empathy_sign     = $sgn($row['ai_dimbal_empathy_sign']);
                        $th_practical_score  = $toF($row['ai_dimbal_practical_thinking_score']);
                        $th_practical_sign   = $sgn($row['ai_dimbal_practical_thinking_sign']);
                        $th_systems_score    = $toF($row['ai_dimbal_systems_judgment_score']);
                        $th_systems_sign     = $sgn($row['ai_dimbal_systems_judgment_sign']);
                        $th_selfest_score    = $toF($row['ai_dimbal_self_esteem_score']);
                        $th_selfest_sign     = $sgn($row['ai_dimbal_self_esteem_sign']);
                        $th_roleaware_score  = $toF($row['ai_dimbal_role_awareness_score']);
                        $th_roleaware_sign   = $sgn($row['ai_dimbal_role_awareness_sign']);
                        $th_selfdir_score    = $toF($row['ai_dimbal_self_direction_score']);
                        $th_selfdir_sign     = $sgn($row['ai_dimbal_self_direction_sign']);

                        $stmtThink->bind_param(
                          "ississississs",
                          $equipo_id,
                          $th_empathy_score,   $th_empathy_sign,
                          $th_practical_score, $th_practical_sign,
                          $th_systems_score,   $th_systems_sign,
                          $th_selfest_score,   $th_selfest_sign,
                          $th_roleaware_score, $th_roleaware_sign,
                          $th_selfdir_score,   $th_selfdir_sign
                        );
                        $stmtThink->execute();

                        // COMPETENCIAS (ai_combo_*)
                        foreach ($row as $k => $v) {
                            if (strpos($k, 'ai_combo_') === 0 && $v !== '') {
                                $score = is_numeric($v) ? max(0, min(10, (float)$v)) : null;
                                if ($score === null) continue;

                                $stmtC->bind_param("isd", $equipo_id, $k, $score);
                                $stmtC->execute();
                                $competencies++;
                            }
                        }

                        // Copiar PLANTILLA de imx_config (si existe) a este miembro
                        if ($stmtCfgCopy && !empty($empresa_config_template)) {
                            foreach ($empresa_config_template as $codeTpl => $valTpl) {
                                $stmtCfgCopy->bind_param("isi", $equipo_id, $codeTpl, $valTpl);
                                $stmtCfgCopy->execute();
                            }
                        }

                        $imported++;
                    } // fin foreach líneas

                    // Ordena catálogo de competencias para la UI
                    if (!empty($all_competencias)) {
                        sort($all_competencias, SORT_NATURAL | SORT_FLAG_CASE);
                    }

                    if ($dry_run) {
                        $feedback = "Previsualización completada (dry-run): no se escribió nada en BD.";
                        $report = [
                          'total_filas'       => $preview_total,
                          'con_match'         => $preview_match,
                          'sin_match'         => $preview_nomatch,
                          'muestra_sin_match' => $not_found
                        ];
                    } else {
                        $conn->commit();
                        $feedback = "Importación completada con éxito.";
                        $report   = [
                          'filas_importadas'    => $imported,
                          'filas_omitidas'      => $skipped,
                          'competencias_upsert' => $competencies,
                          'sin_match'           => count($not_found),
                          'muestra_sin_match'   => $not_found
                        ];
                    }

                    // Cierre de statements
                    if ($stmtFind)    { $stmtFind->close(); }
                    if ($stmtRaw)     { $stmtRaw->close(); }
                    if ($stmtDisc)    { $stmtDisc->close(); }
                    if ($stmtVal)     { $stmtVal->close(); }
                    if ($stmtThink)   { $stmtThink->close(); }
                    if ($stmtC)       { $stmtC->close(); }
                    if ($stmtCfgCopy) { $stmtCfgCopy->close(); }
                }
            }
        } catch (Throwable $e) {
            if (!$dry_run) {
                try { $conn->rollback(); } catch (Throwable $ignored) {}
            }
            $errors[] = "Error al procesar el CSV: " . htmlspecialchars($e->getMessage());
        }
    }
}







// Datos de UI (marca elegida)
$empresa_actual = null;
if (!empty($_POST['empresa_id'])) {
  foreach ($brands as $b) {
    if ((int)$b['id'] === (int)$_POST['empresa_id']) { $empresa_actual = $b; break; }
  }
} else {
  // Por defecto, primera
  $empresa_actual = $brands[0] ?? null;
}








/* ==== Guardar competencias clave (múltiples filas dinámicas) ==== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_key_comp'])) {
    $equipo_id_post = (int)($_POST['equipo_id'] ?? 0);
$names  = $_POST['key_comp_name']  ?? [];  // array strings (ai_combo_*)
$values = $_POST['key_comp_value'] ?? [];  // array enteros 1–10

// NUEVO: leer la lista de equipo_id importados (bulk) enviada por el form
$equipo_ids_bulk = [];
if (!empty($_POST['equipo_ids_bulk'])) {
    $tmp = json_decode((string)$_POST['equipo_ids_bulk'], true);
    if (is_array($tmp)) {
        $equipo_ids_bulk = array_values(array_unique(array_map('intval', $tmp)));
    }
}

// NUEVO: definir a quién aplicar (todos los del CSV si hay bulk; si no, al seleccionado)
$target_equipo_ids = !empty($equipo_ids_bulk)
    ? $equipo_ids_bulk
    : (($equipo_id_post > 0) ? [$equipo_id_post] : []);

// Ajustar condición de entrada
if (!empty($target_equipo_ids) && is_array($names) && is_array($values)) {

        // Catálogo válido: usa el del request actual si vino, si no intenta cargar de BD
        $valid_catalog = [];
        if (!empty($_POST['catalog'])) {
            // Recibe catálogo enviado por el form (hidden JSON)
            $cat = json_decode((string)$_POST['catalog'], true);
            if (is_array($cat)) {
              foreach ($cat as $c) $valid_catalog[$c] = true;
            }
        } else {
            // Fallback: catálogo desde imx_competency
            $rsCat = $conn->query("SELECT DISTINCT code FROM imx_competency");
            while ($r = $rsCat->fetch_assoc()) {
                $valid_catalog[$r['code']] = true;
            }
        }

        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("
                INSERT INTO imx_config (equipo_id, competencia, valor, created_at, updated_at)
                VALUES (?,?,?, NOW(), NOW())
                ON DUPLICATE KEY UPDATE valor = VALUES(valor), updated_at = VALUES(updated_at)
            ");

            // Guarda filas
            // Guarda filas (evita duplicados en la misma petición)
$used = [];
for ($i = 0; $i < count($names); $i++) {
    $c = trim((string)($names[$i] ?? ''));
    $v = (int)($values[$i] ?? 0);
    if ($c === '' || $v < 1 || $v > 10) continue;
    // Si el catálogo está vacío (dry_run o sin imx_competency), permitimos guardar igualmente.
if (!empty($valid_catalog) && !isset($valid_catalog[$c])) continue;

    if (isset($used[$c])) continue;
    $used[$c] = true;

    // NUEVO: aplica a TODOS los equipos destino (bulk o único)
    foreach ($target_equipo_ids as $eid) {
        if ($eid <= 0) continue;
        $stmt->bind_param("isi", $eid, $c, $v);
        $stmt->execute();
    }
}

            
            
            
            $stmt->close();
            $conn->commit();
            $save_msg = !empty($equipo_ids_bulk)
  ? "✅ Competencias clave guardadas para " . count($target_equipo_ids) . " miembros importados."
  : "✅ Competencias clave guardadas correctamente.";

        } catch (Throwable $e) {
            $conn->rollback();
            $save_msg = "⚠️ Error guardando competencias: " . htmlspecialchars($e->getMessage());
        }
    }
}












// === Determinar equipo seleccionado para configurar ===
$selectedEquipoId = 0;
$selectedEmail    = '';

// Permite mantener selección con ?equipo_id=...
if (isset($_GET['equipo_id'])) {
  $selectedEquipoId = (int)$_GET['equipo_id'];
  $selectedEmail    = $importedEmails[$selectedEquipoId] ?? '';
} elseif (isset($_POST['equipo_id']) && isset($_POST['save_key_comp'])) {
  // al guardar, respeta el seleccionado del form
  $selectedEquipoId = (int)$_POST['equipo_id'];
  $selectedEmail    = $importedEmails[$selectedEquipoId] ?? '';
} elseif (!empty($importedEquipos)) {
  // si hubo import: si solo hay 1, úsalo; si varios, el primero por defecto
  $firstEmail = array_key_first($importedEquipos);
  if ($firstEmail !== null) {
    $selectedEmail = $firstEmail;
    $selectedEquipoId = (int)$importedEquipos[$firstEmail];
  }
}

// === Config existente para prefill ===
$existing_config = [];
if ($selectedEquipoId > 0) {
  $st = $conn->prepare("SELECT competencia, valor FROM imx_config WHERE equipo_id = ?");
  $st->bind_param("i", $selectedEquipoId);
  $st->execute();
  $rs = stmt_get_result($st);
  while ($r = $rs->fetch_assoc()) {
    $existing_config[$r['competencia']] = (int)$r['valor'];
  }
  $st->close();
}










/* ====== CATEGORÍAS ↔ COMPETENCIAS (importadas del Excel original IMX) ====== */
/* 
   - Nombres exactos de categorías del Excel, incluyendo saltos de línea \n.
   - Atributos convertidos a códigos ai_combo_* manteniendo acentos.
   - Compatible con tu flujo actual (save_key_comp_cat → imx_config).
*/

/* Helper: genera ai_combo_* conservando acentos y ñ */
if (!function_exists('to_code_utf')) {
  function to_code_utf($name){
    $s = (string)$name;
    $s = trim(mb_strtolower($s, 'UTF-8'));
    $s = preg_replace('~\s+~u', '_', $s); // convierte espacios y saltos en "_"
    $s = preg_replace('~[^0-9a-z_áéíóúñü]~u', '', $s); // mantiene tildes y ñ
    return 'ai_combo_'.$s;
  }
}

/* === JSON literal generado desde el Excel === */
$rawMap = json_decode(<<<'JSON'



{
  "Actitud Personal\nen el Trabajo": [
    "Sentirse responsable por otros",
    "Seguimiento de direcciones",
    "Manejo del estrés",
    "Persistencia",
    "Responsabilidad personal",
    "Rol de confianza"
  ],
  "Administración de\nla Producción": [
    "Análisis de problemas y situaciones",
    "Gestión de Problemas",
    "Solución de problemas",
    "Programación de proyectos",
    "Orientación a la calidad",
    "Orientación a resultados"
  ],
  "Administración de los\nRecursos Humanos": [
    "Corregir a otros",
    "Desarrollar a otros",
    "Evaluar a otros",
    "Dirección de otros",
    "Establecer metas realistas a otros"
  ],
  "Alcance de\nMetas/Objetivos": [
    "Pensamiento conceptual",
    "Planeación a largo plazo",
    "Solución de problemas",
    "Solución Teórica de Problemas"
  ],
  "Atributos Críticos\npara las Ventas": [
    "Placer por el trabajo",
    "Manejo del rechazo",
    "Manejo del estrés",
    "Persistencia",
    "Responsabilidad personal",
    "Compromiso personal",
    "Orientación a resultados",
    "Confianza en si mismo",
    "Autodisciplina y sentido del deber",
    "Habilidad para el arranque de proyectos"
  ],
  "Autoconciencia": [
    "Autoevaluación",
    "Confianza en si mismo",
    "Autodirección",
    "Autoestima"
  ],
  "Autodirección": [
    "Manejo del estrés",
    "Responsabilidad personal",
    "Fijarse metas personales realistas",
    "Autoevaluación",
    "Confianza en si mismo",
    "Autocontrol",
    "Autodisciplina y sentido del deber"
  ],
  "Autosuficiencia": [
    "Consistencia y confiabilidad",
    "Compromiso personal",
    "Pensamiento proactivo",
    "Habilidad para el arranque de proyectos"
  ],
  "Búsqueda de Talento": [
    "Actitud hacia los demás",
    "Toma equilibrada de decisiones",
    "Punto de vista Empático",
    "Evaluar a otros",
    "Libre de prejuicios"
  ],
  "Calificar": [
    "Toma equilibrada de decisiones",
    "Punta de vista empático",
    "Análisis de problemas y situaciones",
    "Confianza en si mismo"
  ],
  "Capacidad para la Solución de Problemas": [
    "Atención al detalle",
    "Habilidad para Integrar",
    "Toma intuitiva de decisiones",
    "Análisis de problemas y situaciones",
    "Solución de problemas",
    "Uso del sentido común"
  ],
  "Cerrar": [
    "Atención al detalle",
    "Manejo del rechazo",
    "Orientación a resultados",
    "Confianza en si mismo"
  ],
  "Comprender a sus Partidarios": [
    "Punto de vista Empático",
    "Evaluar lo dicho",
    "Expectativas realistas",
    "Entendimiento de la Actitud"
  ],
  "Comunicación con el Cliente": [
    "Evaluar lo dicho",
    "Conciencia humana",
    "Sentido del tiempo",
    "Delegación del control",
    "Entendimiento de la actitud"
  ],
  "Comunicar su Visión": [
    "Transmitir los valores del rol",
    "Compromiso personal",
    "Confianza en si mismo",
    "Autodirección",
    "Autoestima"
  ],
  "Conciencia Social": [
    "Actitud hacia los demás",
    "Punto de vista Empático",
    "Libertad de prejuicios",
    "Expectativas realistas",
    "Delegación del control"
  ],
  "Demostrar": [
    "Organización concreta",
    "Solución de problemas",
    "Programación de Proyectos",
    "Sentido del Tiempo"
  ],
  "Desarrollarse a sí sismo": [
    "Conciencia del rol",
    "Autoevaluación",
    "Confianza en si mismo",
    "Autodirección"
  ],
  "Desarrollo del Talento": [
    "Desarrollar a otros",
    "Obtención de Compromisos",
    "Establecer metas realistas a otros",
    "Entendimiento de las Necesidades",
    "Motivacionales"
  ],
  "Empatía": [
    "Actitud hacia los demás",
    "Evaluar a otros",
    "Persuasión",
    "Relacionarse con otros"
  ],
  "Gestión del Desempeño": [
    "Transmitir los valores del rol",
    "Adquirir compromiso",
    "Conciencia humana",
    "Entender qué motiva"
  ],
  "Guiar a Otros": [
    "Flexibilidad",
    "Pensamiento práctico",
    "Pensamiento proactivo",
    "Autocontrol"
  ],
  "Habilidad para el arranque de Proyectos": [
    "Iniciativa",
    "Persistencia",
    "Empuje",
    "Enfoque a metas y proyectos"
  ],
  "Habilidades Interpersonales": [
    "Actitud hacia los demás",
    "Libertad de prejuicios",
    "Expectativas realistas",
    "Delegación del control",
    "Punto de vista Empático",
    "Autocontrol"
  ],
  "Habilidades Sociales": [
    "Desarrollar a otros",
    "Flexibilidad",
    "Dirección de Otros",
    "Delegación del control"
  ],
  "Habilidades de Comunicación": [
    "Evaluar lo dicho",
    "Libertad de Prejuicios",
    "Manejo del rechazo",
    "Sentido del Tiempo",
    "Entendimiento de la Actitud"
  ],
  "Influir": [
    "Flexibilidad",
    "Persuasión",
    "Entendimiento de la actitud",
    "Entendimiento de las necesidades Motivacionales"
  ],
  "Inspirar a los Demás": [
    "Desarrollar a otros",
    "Obtención de Compromisos",
    "Dirección de Otros",
    "Planeación a largo plazo",
    "Persuasión"
  ],
  "Integridad y Confianza": [
    "Actitud enfocada a la honestidad",
    "Toma equilibrada de decisiones",
    "Respeto por las políticas",
    "Respeto por la propiedad"
  ],
  "Liderar el Talento": [
    "Transmitir los valores del rol",
    "Desarrollar a otros",
    "Conciencia humana",
    "Dirección de otros"
  ],
  "Los Seis Principales": [
    "Punto de vista Empático",
    "Pensamiento Práctico",
    "Conciencia del Rol",
    "Autodirección",
    "Autoestima",
    "Juicio sobre los Sistemas"
  ],
  "Manejo del Rechazo": [
    "Rol de Confianza",
    "Autoevaluación",
    "Autocontrol",
    "Autoestima",
    "Sensibilidad hacia los Demás"
  ],
  "Manejo mental y coraje": [
    "Iniciativa",
    "Persistencia",
    "Empuje",
    "Autodisciplina y sentido del Deber"
  ],
  "Motivadores Personales": [
    "Posesiones materiales",
    "Relaciones personales",
    "Auto mejora",
    "Sentido de pertenencia",
    "Sentido del deber",
    "Reconocimiento y estatus"
  ],
  "Navegar sobre aguas difíciles": [
    "Control emocional",
    "Punto de vista Empático",
    "Evaluar lo dicho",
    "Análisis de problemas y situaciones",
    "Gestión de problemas",
    "Solución de problemas"
  ],
  "Obtención de resultados": [
    "Responsabilidad por los demás",
    "Atención al Detalle",
    "Consistencia y confiabilidad",
    "Compromiso personal",
    "Enfoque a metas y proyectos",
    "Orientación a resultados",
    "Delegación del control",
    "Seguimiento de Direcciones",
    "Ética en el Trabajo",
    "Satisfacer los estándares",
    "Respeto por las políticas"
  ],
  "Orientación al Cuidado del Paciente": [
    "Atención al detalle",
    "Pensamiento Proactivo",
    "Enfoque a metas y proyectos",
    "Orientación a la calidad"
  ],
  "Orientación al equipo de salud": [
    "Actitud hacia los demás",
    "Libre de prejuicios",
    "Relaciones Personales",
    "Delegación del control"
  ],
  "Pensamiento Conceptual": [
    "Pensamiento conceptual",
    "Solución Teórica de Problemas"
  ],
  "Planeación Estratégica": [
    "Organización concreta",
    "Planeación a largo plazo",
    "Pensamiento práctico",
    "Programación de Proyectos",
    "Juicio sobre los Sistemas"
  ],
  "Planeación y Organización": [
    "Pensamiento conceptual",
    "Organización concreta",
    "Planeación a largo plazo",
    "Pensamiento proactivo"
  ],
  "Prever los Resultados": [
    "Pensamiento conceptual",
    "Iniciativa",
    "Persistencia",
    "Enfoque a metas y proyectos",
    "Orientación a resultados"
  ],
  "Prospectar": [
    "Iniciativa",
    "Toma intuitiva de decisiones",
    "Persistencia",
    "Rol de confianza",
    "Habilidad para el Arranque de Proyectos"
  ],
  "Relaciones con el paciente": [
    "Punto de vista Empático",
    "Evaluar lo dicho",
    "Conciencia humana",
    "Relacionarse con los demás"
  ],
  "Relaciones de Colaboración": [
    "Actitud hacia los demás",
    "Evaluar lo Dicho",
    "Conciencia Humana",
    "Delegación del control"
  ],
  "Relación con los otros": [
    "Actitud hacia los demás",
    "Control Emocional",
    "Libertad de prejuicios",
    "Manejo del rechazo",
    "Relacionarse con los demás"
  ],
  "Resolución de Conflictos y Problemas": [
    "Control Emocional",
    "Habilidad para integrar",
    "Toma intuitiva de decisiones",
    "Análisis de problemas y situaciones",
    "Ver problemas potenciales",
    "Uso del sentido común"
  ],
  "Saludar": [
    "Actitud hacia los demás",
    "Iniciativa",
    "Relacionarse con los demás",
    "Sensibilidad hacia los Demás"
  ],
  "Servir a Otros": [
    "Responsabilidad por los demás",
    "Actitud hacia los demás",
    "Punto de vista Empático",
    "Evaluar a otros"
  ],
  "Solución de Problemas": [
    "Toma Equilibrada de decisiones",
    "Pensamiento Conceptual",
    "Habilidad para integrar",
    "Solución de problemas"
  ],
  "Toma de decisiones": [
    "Pensamiento conceptual",
    "Organización concreta",
    "Seguimiento de Direcciones",
    "Toma Intuitiva de decisiones",
    "Solución Teórica de Problemas",
    "Uso del sentido común"
  ],
  "Toma de decisiones (corta)": [
    "Toma equilibrada de decisiones",
    "Pensamiento conceptual",
    "Rol de Confianza",
    "Solución Teórica de Problemas"
  ],
  "Trabajo en Equipo": [
    "Actitud hacia los demás",
    "Punto de vista Empático",
    "Libertad de Prejuicios",
    "Delegación del Control"
  ],
  "Visión Guiadora": [
    "Creatividad",
    "Flexibilidad",
    "Habilidad para Integrar",
    "Pensamiento Proactivo",
    "Ver Problemas Potenciales"
  ],
  "Ética en el Trabajo": [
    "Toma equilibrada de decisiones",
    "Ética en el trabajo",
    "Satisfacer los estándares",
    "Respeto por las políticas"
  ]
}



JSON, true);

/* === Construcción del mapa final PHP (Categoría Excel → [ai_combo_*...]) === */
$CATEGORIAS_MAP = [];
foreach ($rawMap as $categoriaExcel => $atributos) {
  if (!is_array($atributos) || empty($atributos)) continue;
  $codes = [];
  foreach ($atributos as $attrName) {
    $attrName = trim((string)$attrName);
    if ($attrName === '') continue;
    $codes[] = to_code_utf($attrName);
  }
  $CATEGORIAS_MAP[$categoriaExcel] = $codes;
}










/* ==== Guardar competencias por CATEGORÍA (aplica a todos los equipos importados) ==== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_key_comp_cat'])) {
    // 1) Datos de entrada
    $cat_names  = $_POST['cat_name']  ?? [];
    $cat_values = $_POST['cat_value'] ?? [];

    // 2) Bulk de IDs de equipo (los que matchearon en este CSV)
    $equipo_ids_bulk = [];
    if (!empty($_POST['equipo_ids_bulk'])) {
        $tmp = json_decode((string)$_POST['equipo_ids_bulk'], true);
        if (is_array($tmp)) {
            $equipo_ids_bulk = array_values(array_unique(array_map('intval', $tmp)));
        }
    }

    // 3) Mapa de categorías → competencias
    $CATS = $CATEGORIAS_MAP;
    if (!empty($_POST['categorias_json'])) {
        $fromForm = json_decode((string)$_POST['categorias_json'], true);
        if (is_array($fromForm)) $CATS = $fromForm;
    }

    // 4) Catálogo válido (competencias detectadas en esta sesión o en BD)
    $valid_catalog = [];
    if (!empty($all_competencias)) {
        foreach ($all_competencias as $cc) $valid_catalog[$cc] = true;
    } else {
        $rsCat = $conn->query("SELECT DISTINCT code FROM imx_competency");
        while ($r = $rsCat->fetch_assoc()) $valid_catalog[$r['code']] = true;
    }

    // 5) Determinar equipos destino (prioridad: bulk → seleccionado → todos de la empresa)
    $target_equipo_ids = [];
    if (!empty($equipo_ids_bulk)) {
        $target_equipo_ids = $equipo_ids_bulk;
    }
    if (empty($target_equipo_ids)) {
        $equipo_id_post = (int)($_POST['equipo_id'] ?? 0);
        if ($equipo_id_post > 0) $target_equipo_ids = [$equipo_id_post];
    }
    if (empty($target_equipo_ids)) {
        $empresa_ctx = (int)($_POST['empresa_id_ctx'] ?? 0);
        if ($empresa_ctx > 0) {
            $resAll = $conn->prepare("SELECT id FROM equipo WHERE usuario_id = ?");
            $resAll->bind_param("i", $empresa_ctx);
            $resAll->execute();
            $rsAll = stmt_get_result($resAll);
            while ($r = $rsAll->fetch_assoc()) $target_equipo_ids[] = (int)$r['id'];
            $resAll->close();
        }
    }

    // 6) Validaciones mínimas
    if (empty($target_equipo_ids)) {
        $errors[] = "No hay miembros destino para aplicar las categorías.";
    }
    if (empty($cat_names) || empty($cat_values)) {
        $errors[] = "No se enviaron categorías para guardar.";
    }

    // 7) Escritura en BD: expande categorías → competencias y guarda en imx_config
    if (empty($errors)) {
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("
                INSERT INTO imx_config (equipo_id, competencia, valor, created_at, updated_at)
                VALUES (?,?,?, NOW(), NOW())
                ON DUPLICATE KEY UPDATE valor = VALUES(valor), updated_at = VALUES(updated_at)
            ");

            // Recorre todas las filas enviadas (categoría + valor)
            for ($i = 0; $i < count($cat_names); $i++) {
                $cat = trim((string)($cat_names[$i] ?? ''));
                $val = (int)($cat_values[$i] ?? 0);

                if ($cat === '' || $val < 1 || $val > 10) continue;
                if (empty($CATS[$cat]) || !is_array($CATS[$cat])) continue;

                // Competencias de la categoría
                $competencias_cat = $CATS[$cat];

                foreach ($competencias_cat as $code) {
                    $code = trim((string)$code);
                    if ($code === '') continue;

                    // --- Quita la validación por catálogo ---
// if (!empty($valid_catalog) && !isset($valid_catalog[$code])) continue;


                    // Aplica a todos los equipos destino
                    foreach ($target_equipo_ids as $eid) {
                        if ($eid <= 0) continue;
                        $stmt->bind_param("isi", $eid, $code, $val);
                        $stmt->execute();
                    }
                }
            }

            $stmt->close();
            $conn->commit();

            // Aviso corto de confirmación para la UI
            $feedback = "✅ Información procesada y aplicada a los usuarios importados.";
        } catch (Throwable $e) {
            $conn->rollback();
            $errors[] = "Error guardando categorías: " . htmlspecialchars($e->getMessage());
        }
    }
}













?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <title>Importar Innermetrix — Valírica</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <!-- Tipografía y estilos del dashboard -->
  <style>
    @import url("https://use.typekit.net/qrv8fyz.css");
    :root {
      --c-primary:#012133;
      --c-secondary:#184656;
      --c-accent:#EF7F1B;
      --c-soft:#FFF5F0;
      --c-body:#474644;
      --c-bg:#FFFFFF;
      --radius:20px;
      --shadow:0 6px 20px rgba(0,0,0,0.06);
    }
    * { box-sizing:border-box; margin:0; padding:0; }
    html, body { background:var(--c-bg); color:var(--c-body);
      font-family:"gelica", system-ui, -apple-system, Segoe UI, Roboto, "Helvetica Neue", Arial, sans-serif; }
    a { color:var(--c-accent); text-decoration:none; }
    a:hover { text-decoration:underline; }
    header {
      width:100%; background:var(--c-primary); color:var(--c-soft);
      padding:14px 32px; display:flex; align-items:center; justify-content:space-between;
      box-shadow:0 3px 12px rgba(0,0,0,0.08);
    }
    .nav-left { display:flex; align-items:center; gap:14px; }
    .brand-logo { width:40px; height:40px; border-radius:10px; object-fit:cover; background:#f4f4f4; box-shadow:0 2px 6px rgba(0,0,0,0.25); }
    .title { display:flex; flex-direction:column; }
    .title h1 { margin:0; font-size:clamp(18px,2.4vw,24px); color:var(--c-soft); letter-spacing:-0.3px; line-height:1.1; }
    .title span { font-size:13px; color:var(--c-soft); opacity:0.8; }
    .wrap { padding:32px clamp(16px,3vw,40px); max-width:1100px; margin:0 auto; }
    .card {
      background:#fff; border-radius:var(--radius); box-shadow:var(--shadow);
      padding:24px; border:1px solid #f1f1f1; margin-bottom:24px;
    }
    .card h3 { margin:0 0 12px; color:var(--c-secondary); font-size:clamp(16px,2vw,20px); }
    .row { display:grid; grid-template-columns: 1fr; gap:16px; }
    .grid-2 { display:grid; grid-template-columns: 1fr 1fr; gap:16px; }
    @media (max-width: 880px){ .grid-2 { grid-template-columns: 1fr; } }
    label { font-weight:700; color:var(--c-secondary); font-size:14px; }
    select, input[type="file"] {
      width:100%; padding:12px; border-radius:12px; border:1px solid #e7e7e7; background:#fff; font-size:14px;
    }
    .btn { display:inline-flex; align-items:center; gap:8px; padding:12px 16px;
      border-radius:12px; font-weight:700; font-size:14px; text-decoration:none; cursor:pointer; }
    .btn-primary { background:var(--c-accent); color:#fff; border:1px solid rgba(0,0,0,0.06); }
    .btn-ghost   { background:var(--c-soft); color:var(--c-secondary); border:1px solid rgba(1,33,51,0.12); }
    .btn:disabled { opacity:0.65; cursor:not-allowed; }
    .muted { color:#777; font-size:13px; }
    .alert-ok { background: #ecfff4; border:1px solid #b4f0cf; color:#094c2e; padding:12px 14px; border-radius:12px; }
    .alert-err{ background: #fff0f0; border:1px solid #ffd3d3; color:#7a0b0b; padding:12px 14px; border-radius:12px; }
    ul.list { list-style:none; padding-left:0; display:grid; gap:6px; }
    .chip { display:inline-flex; gap:6px; align-items:center; padding:4px 10px; border-radius:9999px;
      border:1px solid rgba(1,33,51,0.08); background:var(--c-soft); color:var(--c-secondary); font-size:12px; }
    .footer-actions { display:flex; gap:10px; flex-wrap:wrap; }
  </style>
</head>
<body>

<header>
  <div class="nav-left">
    <img class="brand-logo" src="<?php echo htmlspecialchars(($empresa_actual['logo'] ?? '/uploads/logo-192.png')); ?>" alt="Logo">
    <div class="title">
      <h1>Importar Innermetrix</h1>
      <span>Asocia los resultados a una marca y súbelos por CSV.</span>
    </div>
  </div>

  <div class="header-actions">
    <a class="btn btn-ghost" href="a-desktop-dashboard-brand.php">Volver al dashboard</a>
  </div>
</header>

<div class="wrap">

  <!-- Card: Selección de marca y archivo -->
  <div class="card">
    <h3>1) Selecciona marca y archivo</h3>
    <form method="post" enctype="multipart/form-data" class="row">
      <div class="grid-2">
        <div>
          <label for="empresa_id">Marca / Empresa</label>
          <select id="empresa_id" name="empresa_id" required>
            <option value="">— Selecciona —</option>
            <?php foreach ($brands as $b): ?>
              <option value="<?php echo (int)$b['id']; ?>" <?php echo (!empty($_POST['empresa_id']) && (int)$_POST['empresa_id']===(int)$b['id']) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($b['empresa'] ?: ('Empresa #'.$b['id'])); ?>
              </option>
            <?php endforeach; ?>
          </select>
          <p class="muted">Se usará este <strong>usuario_id</strong> para matchear <code>equipo.usuario_id</code> + <code>equipo.correo</code>.</p>
        </div>

        <div>
          <label for="imx_csv">Archivo CSV de Innermetrix</label>
          <input type="file" id="imx_csv" name="imx_csv" accept=".csv" required />
          <p class="muted">Asegúrate que el CSV tenga la columna <code>email</code> y las columnas DISC/VALUES/THINKING/ai_combo_*</p>
        </div>
      </div>

      <div>
        <label style="display:flex;align-items:center;gap:8px;">
          <input type="checkbox" name="dry_run" value="1" <?php echo isset($_POST['dry_run']) ? 'checked' : ''; ?> />
          Hacer previsualización (no escribir en BD)
        </label>
        <p class="muted">La previsualización te dirá cuántas filas matchean por correo antes de importar.</p>
      </div>

      <div class="footer-actions">
        <button type="submit" class="btn btn-primary">Procesar archivo</button>
        <a href="a-innermetrix-import.php" class="btn btn-ghost">Limpiar</a>
      </div>
    </form>
  </div>

  <!-- Card: Resultado -->
  <?php if ($feedback || $errors): ?>
    <div class="card">
      <h3>2) Resultado</h3>

      <?php if ($feedback): ?>
        <div class="alert-ok" style="margin-bottom:12px;"><?php echo $feedback; ?></div>
      <?php endif; ?>

      <?php if ($errors): ?>
        <div class="alert-err" style="margin-bottom:12px;">
          <strong>Errores detectados:</strong>
          <ul class="list">
            <?php foreach ($errors as $e): ?>
              <li>• <?php echo $e; ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <?php if ($report): ?>
        <div style="display:grid; gap:12px;">
          <?php if (isset($report['total_filas'])): ?>
            <div class="chip">Filas en CSV: <strong><?php echo (int)$report['total_filas']; ?></strong></div>
            <div class="chip">Con match: <strong><?php echo (int)$report['con_match']; ?></strong></div>
            <div class="chip">Sin match: <strong><?php echo (int)$report['sin_match']; ?></strong></div>
          <?php endif; ?>

          <?php if (isset($report['filas_importadas'])): ?>
            <div class="chip">Filas importadas: <strong><?php echo (int)$report['filas_importadas']; ?></strong></div>
            <div class="chip">Filas omitidas: <strong><?php echo (int)$report['filas_omitidas']; ?></strong></div>
            <div class="chip">Competencias upsert: <strong><?php echo (int)$report['competencias_upsert']; ?></strong></div>
            <div class="chip">Sin match (correos): <strong><?php echo (int)$report['sin_match']; ?></strong></div>
          <?php endif; ?>

          <?php if (!empty($report['muestra_sin_match'])): ?>
            <div>
              <label>Correos sin match (muestra máx. 25):</label>
              <ul class="list">
                <?php foreach ($report['muestra_sin_match'] as $em): ?>
                  <li><code><?php echo htmlspecialchars($em); ?></code></li>
                <?php endforeach; ?>
              </ul>
              <p class="muted">Revisa que esos correos existan en <code>equipo.correo</code> para la marca seleccionada.</p>
            </div>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <!-- Card: Ayuda rápida -->
  <div class="card">
    <h3>Ayuda rápida</h3>
    <p style="margin-top:8px;">
      • El importador hace match por <strong>empresa seleccionada</strong> (usuario_id) + <strong>correo</strong> del miembro.<br/>
      • Escribe en tablas: <code>imx_raw</code>, <code>imx_disc</code>, <code>imx_values</code>, <code>imx_thinking</code>, <code>imx_competency</code> (upsert).<br/>
      • Puedes usar “Previsualización” para validar el impacto antes de escribir en la BD.
    </p>
  </div>

</div>












<?php if (!empty($importedEquipos)): ?>
<div class="card" style="margin-top:30px;">
  <h3>3b) Competencias por Categoría (aplica a TODOS los importados)</h3>
  <p class="muted" style="margin-bottom:12px;">
    Elige una <strong>categoría</strong> y asígnale un <strong>valor 1–10</strong>. Se aplicará a todas las competencias de esa categoría para
    <strong>todos los miembros importados de este CSV</strong>.
  </p>



  <form method="post" action="">
    <input type="hidden" name="save_key_comp_cat" value="1">
    <!-- IDs de todos los equipos importados en esta carga -->
    <input type="hidden" name="equipo_ids_bulk"
           value="<?php echo htmlspecialchars(json_encode(array_values($importedEquipos))); ?>">
           
           
           
           <input type="hidden" name="empresa_id_ctx" value="<?php echo (int)($_POST['empresa_id'] ?? 0); ?>">
<input type="hidden" name="equipo_id" value="<?php echo (int)$selectedEquipoId; ?>">

           
           
    <!-- Enviamos el mapa de categorías al backend para validación -->
    <input type="hidden" name="categorias_json"
           value="<?php echo htmlspecialchars(json_encode($CATEGORIAS_MAP, JSON_UNESCAPED_UNICODE)); ?>">

    <div id="cat-rows" style="display:grid; gap:12px;">
      <!-- fila inicial -->
      <div class="cat-row" style="display:grid; grid-template-columns:1fr 120px 40px; gap:8px; align-items:center;">
        <select name="cat_name[]" required
        style="padding:10px; border:1px solid #ddd; border-radius:10px;">
  <option value="">— Selecciona categoría —</option>
  <?php foreach (array_keys($CATEGORIAS_MAP) as $cat): ?>
    <option value="<?php echo htmlspecialchars($cat); ?>">
      <?php echo htmlspecialchars($cat); ?>
    </option>
  <?php endforeach; ?>
</select>

        <input name="cat_value[]" type="number" min="1" max="10" step="1" placeholder="Valor"
               required style="padding:10px; border:1px solid #ddd; border-radius:10px;">
        <button type="button" class="btn-cat-del"
                style="border:0; background:#FFF5F0; color:#EF7F1B; border-radius:10px; width:40px; height:40px; cursor:pointer;">✕</button>
      </div>
    </div>

    <div style="margin-top:12px;">
      <button type="button" id="btn-add-cat"
              style="background:#184656; color:#fff; padding:10px 14px; border:0; border-radius:10px; cursor:pointer;">
        + Categoría
      </button>
    </div>

    <div style="margin-top:16px;">
      <button type="submit"
              style="background:#012133; color:#fff; padding:10px 16px; border:0; border-radius:10px; cursor:pointer;">
        Guardar categorías → competencias
      </button>
    </div>
  </form>
</div>

<script>
(function(){
  const rows = document.getElementById('cat-rows');
  const btnAdd = document.getElementById('btn-add-cat');

  function makeRow() {
    const row = document.createElement('div');
    row.className = 'cat-row';
    row.style.cssText = 'display:grid; grid-template-columns:1fr 120px 40px; gap:8px; align-items:center;';

    // SELECT de categorías (server-side options renderizadas en plantilla)
    const sel = document.createElement('select');
    sel.name = 'cat_name[]';
    sel.required = true;
    sel.style.cssText = 'padding:10px; border:1px solid #ddd; border-radius:10px;';
    sel.innerHTML = `
      <option value="">— Selecciona categoría —</option>
      <?php foreach (array_keys($CATEGORIAS_MAP) as $cat): ?>
        <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option>
      <?php endforeach; ?>
    `;

    const inpVal = document.createElement('input');
    inpVal.name = 'cat_value[]';
    inpVal.type = 'number'; inpVal.min = '1'; inpVal.max = '10'; inpVal.step = '1';
    inpVal.placeholder = 'Valor';
    inpVal.required = true;
    inpVal.style.cssText = 'padding:10px; border:1px solid #ddd; border-radius:10px;';

    const btnDel = document.createElement('button');
    btnDel.type = 'button';
    btnDel.className = 'btn-cat-del';
    btnDel.textContent = '✕';
    btnDel.style.cssText = 'border:0; background:#FFF5F0; color:#EF7F1B; border-radius:10px; width:40px; height:40px; cursor:pointer;';
    btnDel.addEventListener('click', function(){ row.remove(); });

    row.appendChild(sel); row.appendChild(inpVal); row.appendChild(btnDel);
    return row;
  }

  btnAdd.addEventListener('click', function(){ rows.appendChild(makeRow()); });

  // Delegación para borrar filas creadas dinámicamente
  rows.addEventListener('click', function(e){
    if (e.target.classList.contains('btn-cat-del')) {
      e.target.closest('.cat-row')?.remove();
    }
  });
})();
</script>

<?php endif; ?>


</body>
</html>
