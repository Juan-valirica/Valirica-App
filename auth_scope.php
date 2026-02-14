<?php
/**
 * auth_scope.php — Helpers de acceso y “scope” de empresa
 * Requisitos:
 *   - Debe cargarse DESPUÉS de `session_start()` y `config.php` (para $conn y APP_SIGN_KEY).
 *   - En los consumer files, usar siempre: require_once 'auth_scope.php';
 */

if (defined('AUTH_SCOPE_LOADED')) return;
define('AUTH_SCOPE_LOADED', true);

/* ===================== Helpers básicos ===================== */

if (!function_exists('h')) {
  function h($v){
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
  }
}

/** Obtiene [user_id, role] desde la sesión; fuerza login si no hay sesión */
if (!function_exists('current_user')) {
  function current_user(): array {
    $uid  = (int)($_SESSION['user_id'] ?? 0);
    $role = (string)($_SESSION['role'] ?? '');
    if ($uid <= 0) { header("Location: login.php"); exit; }
    return [$uid, $role];
  }
}

/** ¿El provider (viewer) puede acceder a la company? (valida usuarios.provider_id) */
if (!function_exists('provider_can_access_company')) {
  function provider_can_access_company(mysqli $conn, int $provider_user_id, int $company_id): bool {
    $stmt = $conn->prepare("SELECT 1 FROM usuarios WHERE id = ? AND provider_id = ? LIMIT 1");
    $stmt->bind_param("ii", $company_id, $provider_user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    return ($res && $res->num_rows > 0);
  }
}

/* ===================== Firma navegación provider → company ===================== */

if (!function_exists('make_company_sig')) {
  function make_company_sig(int $company_id, int $viewer_id, string $csrf): string {
    // APP_SIGN_KEY debe existir en config.php
    $data = $company_id . '|' . $viewer_id . '|as_provider_v1';
    return hash_hmac('sha256', $data, APP_SIGN_KEY . $csrf);
  }
}

if (!function_exists('verify_company_sig')) {
  function verify_company_sig(int $company_id, int $viewer_id, string $csrf, string $sig): bool {
    $calc = make_company_sig($company_id, $viewer_id, $csrf);
    return hash_equals($calc, $sig);
  }
}

/* ===================== Scope de empresa seguro ===================== */
/**
 * Resuelve el scope de empresa según rol:
 * - provider: requiere ?company_id y ?sig válidos y pertenencia en BD
 * - company_admin: su propia empresa (id del usuario en `usuarios`)
 * - employee: deduce empresa desde `equipo.user_id → empresa_id`
 */
if (!function_exists('resolve_company_scope_or_403')) {
  function resolve_company_scope_or_403(mysqli $conn): int {
    list($uid, $role) = current_user();

    if ($role === 'provider') {
      $company_id = (int)($_GET['company_id'] ?? 0);
      $sig        = (string)($_GET['sig'] ?? '');
      if ($company_id <= 0) {
        http_response_code(400); exit('Bad Request: company_id missing');
      }

      // Verifica firma
      $csrf = (string)($_SESSION['csrf_token'] ?? '');
      if ($sig === '' || $csrf === '' || !verify_company_sig($company_id, $uid, $csrf, $sig)) {
        http_response_code(403); exit('Forbidden: invalid signature');
      }

      // Verifica relación provider → company
      if (!provider_can_access_company($conn, $uid, $company_id)) {
        http_response_code(403); exit('Forbidden: no access to this company');
      }

      return $company_id;
    }

    if ($role === 'company_admin') {
      return (int)$uid;
    }

    if ($role === 'employee') {
      $stmt = $conn->prepare("
        SELECT e.empresa_id
        FROM equipo e
        WHERE e.user_id = ?
        LIMIT 1
      ");
      $stmt->bind_param("i", $uid);
      $stmt->execute();
      $res = $stmt->get_result();
      if (!$res || !$res->num_rows) {
        http_response_code(403); exit('Forbidden: employee not linked to a company');
      }
      $row = $res->fetch_assoc();
      $empresa_id = (int)($row['empresa_id'] ?? 0);
      if ($empresa_id <= 0) {
        http_response_code(403); exit('Forbidden: invalid company scope');
      }
      return $empresa_id;
    }

    http_response_code(403); exit('Forbidden: invalid role');
  }
}

/* ===================== UI helpers ===================== */

if (!function_exists('get_company_header')) {
  function get_company_header(mysqli $conn, int $company_id): ?array {
    $stmt = $conn->prepare("SELECT id, empresa, logo FROM usuarios WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $company_id);
    $stmt->execute();
    $res = $stmt->get_result();
    return ($res && $res->num_rows) ? $res->fetch_assoc() : null;
  }
}

