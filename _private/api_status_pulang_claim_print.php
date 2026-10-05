<?php
declare(strict_types=1);

require_once __DIR__ . '/lib_auth.php';
require_once __DIR__ . '/lib_csrf.php';
require_once __DIR__ . '/lib_eklaim.php';

function claim_print_error(string $message, int $status): void {
  http_response_code($status);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(['ok'=>false, 'message'=>$message], JSON_UNESCAPED_UNICODE);
  exit;
}

function claim_print_find_pdf($value): ?string {
  if (is_string($value) && $value !== '') {
    $candidate = preg_replace('/^data:application\/pdf;base64,/i', '', trim($value));
    $decoded = base64_decode((string)$candidate, true);
    if (is_string($decoded) && strncmp($decoded, '%PDF-', 5) === 0) return $decoded;
    if (strncmp($value, '%PDF-', 5) === 0) return $value;
  }
  if (is_array($value)) {
    foreach ($value as $item) {
      $pdf = claim_print_find_pdf($item);
      if ($pdf !== null) return $pdf;
    }
  }
  return null;
}

if (!auth_user()) claim_print_error('Sesi login telah berakhir.', 401);
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') claim_print_error('Method tidak diizinkan.', 405);
if (!csrf_validate((string)($_POST['_csrf'] ?? ''), 'status_pulang_claim_print', false)) {
  claim_print_error('Token keamanan tidak valid.', 419);
}

$noRawat = trim((string)($_POST['no_rawat'] ?? ''));
$noSep = strtoupper(trim((string)($_POST['nosep'] ?? '')));
if (!preg_match('/^[0-9]{4}\/[0-9]{2}\/[0-9]{2}\/[0-9]{6}$/', $noRawat)
    || !preg_match('/^[A-Z0-9]{10,40}$/', $noSep)) {
  claim_print_error('Nomor rawat atau SEP tidak valid.', 422);
}

$stmt = pdo()->prepare('SELECT 1 FROM bridging_sep WHERE no_rawat=:no_rawat AND no_sep=:nosep LIMIT 1');
$stmt->execute(['no_rawat'=>$noRawat, 'nosep'=>$noSep]);
if (!$stmt->fetchColumn()) claim_print_error('SEP tidak ditemukan untuk nomor rawat tersebut.', 404);

try {
  $response = eklaim_claim_print($noSep);
  $pdf = claim_print_find_pdf($response);
  if ($pdf === null) {
    $metadata = isset($response['metadata']) && is_array($response['metadata']) ? $response['metadata'] : [];
    $message = (string)($metadata['message'] ?? $metadata['error'] ?? 'PDF claim_print tidak ditemukan pada respons E-Klaim.');
    throw new RuntimeException($message);
  }
  header('Content-Type: application/pdf');
  header('Content-Disposition: inline; filename="claim-'.$noSep.'.pdf"');
  header('Content-Length: '.strlen($pdf));
  echo $pdf;
} catch (Throwable $e) {
  error_log('Claim print ['.$noSep.']: '.$e->getMessage());
  claim_print_error($e->getMessage(), 502);
}
