<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

function eklaim_setting(string $field): string {
  $stmt = pdo()->prepare("SELECT value FROM mlite_settings WHERE module = 'vedika' AND field = :field LIMIT 1");
  $stmt->execute(['field' => $field]);
  return trim((string)($stmt->fetchColumn() ?: ''));
}

function eklaim_encrypt(string $data, string $hexKey): string {
  $key = hex2bin($hexKey);
  if ($key === false || strlen($key) !== 32) {
    throw new RuntimeException('Kunci E-Klaim tidak valid.');
  }

  $ivSize = openssl_cipher_iv_length('aes-256-cbc');
  $iv = random_bytes($ivSize);
  $encrypted = openssl_encrypt($data, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
  if ($encrypted === false) throw new RuntimeException('Enkripsi permintaan E-Klaim gagal.');

  $signature = substr(hash_hmac('sha256', $encrypted, $key, true), 0, 10);
  return chunk_split(base64_encode($signature . $iv . $encrypted));
}

function eklaim_decrypt_candidate(string $candidate, string $hexKey): ?array {
  $key = hex2bin($hexKey);
  if ($key === false || strlen($key) !== 32) return null;

  $decoded = base64_decode($candidate, true);
  if ($decoded === false) return null;

  $ivSize = openssl_cipher_iv_length('aes-256-cbc');
  if (strlen($decoded) <= 10 + $ivSize) return null;

  $signature = substr($decoded, 0, 10);
  $iv = substr($decoded, 10, $ivSize);
  $encrypted = substr($decoded, 10 + $ivSize);
  $calculated = substr(hash_hmac('sha256', $encrypted, $key, true), 0, 10);
  if (!hash_equals($signature, $calculated)) return null;

  $plain = openssl_decrypt($encrypted, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
  if (!is_string($plain)) return null;
  $json = json_decode($plain, true);
  return is_array($json) ? $json : null;
}

function eklaim_decode_response(string $response, string $hexKey): array {
  $trimmed = preg_replace('/^\xEF\xBB\xBF/', '', trim($response));
  $plain = json_decode((string)$trimmed, true);
  if (is_array($plain)) return $plain;
  if (is_string($plain)) {
    $nested = json_decode($plain, true);
    if (is_array($nested)) return $nested;
  }

  $normalized = str_replace(["\r\n", "\r"], "\n", (string)$trimmed);
  $candidates = [$normalized];
  $payloadLines = [];
  foreach (preg_split('/\n/', $normalized) ?: [] as $line) {
    $line = trim($line);
    if ($line === '' || preg_match('/^-{2,}(?:BEGIN|END)\b/i', $line)) continue;
    $payloadLines[] = $line;
  }
  if ($payloadLines) $candidates[] = implode('', $payloadLines);

  foreach (array_unique($candidates) as $candidate) {
    $decoded = eklaim_decrypt_candidate($candidate, $hexKey);
    if (is_array($decoded)) return $decoded;
  }
  throw new RuntimeException('Respons E-Klaim tidak valid atau tidak dapat didekripsi.');
}

function eklaim_get_claim_data(string $noSep): array {
  $url = eklaim_setting('eklaim_url');
  $key = eklaim_setting('eklaim_key');
  if ($url === '' || $key === '') throw new RuntimeException('Pengaturan E-Klaim belum lengkap.');

  $payload = json_encode([
    'metadata' => ['method' => 'get_claim_data'],
    'data' => ['nomor_sep' => $noSep],
  ], JSON_UNESCAPED_SLASHES);
  if (!is_string($payload)) throw new RuntimeException('Gagal membuat permintaan E-Klaim.');

  $ch = curl_init();
  curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_HEADER => false,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => eklaim_encrypt($payload, $key),
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT => 30,
  ]);
  $response = curl_exec($ch);
  if ($response === false) {
    $message = curl_error($ch);
    curl_close($ch);
    throw new RuntimeException('Koneksi E-Klaim gagal: ' . $message);
  }
  $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  if ($httpCode >= 400) throw new RuntimeException('E-Klaim mengembalikan HTTP ' . $httpCode . '.');

  return eklaim_decode_response((string)$response, $key);
}

function eklaim_claim_print(string $noSep): array {
  $url = eklaim_setting('eklaim_url');
  $key = eklaim_setting('eklaim_key');
  if ($url === '' || $key === '') throw new RuntimeException('Pengaturan E-Klaim belum lengkap.');

  $payload = json_encode([
    'metadata' => ['method' => 'claim_print'],
    'data' => ['nomor_sep' => $noSep],
  ], JSON_UNESCAPED_SLASHES);
  if (!is_string($payload)) throw new RuntimeException('Gagal membuat permintaan claim_print.');

  $ch = curl_init();
  curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_HEADER => false,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => eklaim_encrypt($payload, $key),
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT => 60,
  ]);
  $response = curl_exec($ch);
  if ($response === false) {
    $message = curl_error($ch);
    curl_close($ch);
    throw new RuntimeException('Koneksi E-Klaim gagal: ' . $message);
  }
  $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  if ($httpCode >= 400) throw new RuntimeException('E-Klaim mengembalikan HTTP ' . $httpCode . '.');

  return eklaim_decode_response((string)$response, $key);
}

function eklaim_dc_status(array $response): array {
  $envelope = isset($response['response']) && is_array($response['response'])
    ? $response['response'] : $response;
  $data = isset($envelope['data']) && is_array($envelope['data']) ? $envelope['data'] : [];
  $kemenkes = strtolower(trim((string)($data['kemenkes_dc_status_cd'] ?? '')));
  $bpjs = strtolower(trim((string)($data['bpjs_dc_status_cd'] ?? '')));

  return [
    'sent' => $kemenkes === 'sent' && $bpjs === 'sent',
    'kemenkes' => $kemenkes !== '' ? $kemenkes : 'belum',
    'bpjs' => $bpjs !== '' ? $bpjs : 'belum',
    'claim' => strtolower(trim((string)($data['klaim_status_cd'] ?? ''))),
  ];
}
