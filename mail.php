<?php
/**
 * Taurus Construtora — Mailer seguro
 * Proteções: honeypot, rate limit, origin check,
 *            header injection, sanitização estrita.
 *
 * !! ALTERE $destinatario e $dominios_permitidos !!
 */

header('Content-Type: application/json; charset=utf-8');

// ─── CONFIGURAÇÃO ───────────────────────────────────────────
$destinatario       = 'contato@construtoraturusmt.com.br';
$remetente_nome     = 'Construtora Taurus';
$remetente_mail     = 'contato@construtorataurusmt.com.br';

// Domínios autorizados a enviar o formulário
$dominios_permitidos = [
    'construtorataurusmt.com.br',
    'www.construtorataurusmt.com.br',
];

// Rate limit: máx. requisições por janela de tempo por IP
$rl_max       = 5;          // máximo de envios
$rl_janela    = 3600;       // janela em segundos (1 hora)
$tmp_dir      = __DIR__ . '/tmp/';
// ────────────────────────────────────────────────────────────

// --- Apenas POST ------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['success' => false, 'message' => 'Método não permitido.']));
}

// --- Content-Type deve ser form data ----------------------------
$ct = $_SERVER['CONTENT_TYPE'] ?? '';
if (stripos($ct, 'application/x-www-form-urlencoded') === false
    && stripos($ct, 'multipart/form-data') === false) {
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'Requisição inválida.']));
}

// --- Verificação de Origin / Referer ----------------------------
$origin  = parse_url($_SERVER['HTTP_ORIGIN']  ?? '', PHP_URL_HOST);
$referer = parse_url($_SERVER['HTTP_REFERER'] ?? '', PHP_URL_HOST);
$host    = $origin ?: $referer;

// Remove www. para comparação
$host_limpo = preg_replace('/^www\./', '', strtolower($host ?? ''));
$permitido  = array_map(fn($d) => preg_replace('/^www\./', '', $d), $dominios_permitidos);

if (!empty($dominios_permitidos) && !in_array($host_limpo, $permitido, true)) {
    http_response_code(403);
    die(json_encode(['success' => false, 'message' => 'Acesso negado.']));
}

// --- Honeypot ---------------------------------------------------
// Campo "website" é invisível para humanos; bots preenchem.
if (!empty($_POST['website'])) {
    // Simula sucesso para não revelar a proteção
    die(json_encode(['success' => true, 'message' => 'Mensagem enviada com sucesso!']));
}

// --- Rate Limiting (por IP, arquivo) ----------------------------
$ip_raw  = $_SERVER['HTTP_CF_CONNECTING_IP']    // Cloudflare
        ?? $_SERVER['HTTP_X_FORWARDED_FOR']
        ?? $_SERVER['REMOTE_ADDR']
        ?? 'unknown';
$ip      = preg_replace('/[^a-f0-9:.\-]/i', '', explode(',', $ip_raw)[0]);
$ip_hash = hash('sha256', $ip);                  // não armazena IP em texto claro
$rl_file = $tmp_dir . 'rl_' . $ip_hash . '.json';

$agora    = time();
$contagem = 1;

if (is_file($rl_file)) {
    $dados = json_decode(file_get_contents($rl_file), true);
    if ($dados && isset($dados['inicio'], $dados['count'])) {
        if ($agora - $dados['inicio'] < $rl_janela) {
            if ($dados['count'] >= $rl_max) {
                $espera = $rl_janela - ($agora - $dados['inicio']);
                http_response_code(429);
                die(json_encode([
                    'success' => false,
                    'message' => "Muitas tentativas. Tente novamente em " . ceil($espera / 60) . " minutos ou use o WhatsApp."
                ]));
            }
            $contagem = $dados['count'] + 1;
        }
    }
}

file_put_contents($rl_file, json_encode([
    'inicio' => $agora,
    'count'  => $contagem,
]), LOCK_EX);

// Limpa arquivos antigos ocasionalmente (1% de chance)
if (rand(1, 100) === 1) {
    foreach (glob($tmp_dir . 'rl_*.json') as $f) {
        $d = json_decode(file_get_contents($f), true);
        if ($d && ($agora - ($d['inicio'] ?? 0)) > $rl_janela) {
            @unlink($f);
        }
    }
}

// --- Sanitização e limites de tamanho ---------------------------
function sanitize(string $valor, int $max): string {
    return mb_substr(trim(strip_tags($valor)), 0, $max);
}

$nome     = sanitize($_POST['nome']     ?? '', 100);
$telefone = sanitize($_POST['telefone'] ?? '', 20);
$tipo     = sanitize($_POST['tipo']     ?? '', 30);
$mensagem = sanitize($_POST['mensagem'] ?? '', 1000);
$email    = mb_substr(trim(filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL)), 0, 150);

// --- Validação --------------------------------------------------
if (empty($nome) || empty($telefone) || empty($tipo)) {
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'Preencha os campos obrigatórios.']));
}

if (!preg_match('/^[\p{L}\s.\-]{2,100}$/u', $nome)) {
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'Nome inválido.']));
}

if (!preg_match('/^[\d\s()\-+]{7,20}$/', $telefone)) {
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'Telefone inválido.']));
}

$tipos_validos = ['residencial', 'comercial', 'industrial', 'reforma', 'outro'];
if (!in_array(strtolower($tipo), $tipos_validos, true)) {
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'Tipo de projeto inválido.']));
}

if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'E-mail inválido.']));
}

// --- Prevenir injeção de headers de e-mail ----------------------
// Remove quebras de linha de todos os campos usados em headers
function safe_header(string $v): string {
    return preg_replace('/[\r\n\t]/', ' ', $v);
}
$nome_safe  = safe_header($nome);
$email_safe = safe_header($email);

// --- Assunto ----------------------------------------------------
$assunto = '=?UTF-8?B?' . base64_encode("Novo Contato — Taurus Construtora | {$nome_safe}") . '?=';

// --- Corpo HTML -------------------------------------------------
$linha_email = !empty($email_safe) ? "
        <tr>
          <td class='label'>E-mail</td>
          <td class='value'><a href='mailto:{$email_safe}' style='color:#C9A84C;'>{$email_safe}</a></td>
        </tr>" : '';

$linha_mensagem = !empty($mensagem) ? "
        <tr>
          <td colspan='2' style='padding-top:20px;'>
            <p class='label' style='margin:0 0 8px;'>Mensagem</p>
            <p style='margin:0;font-size:15px;color:#333;line-height:1.7;background:#f9f9f9;padding:16px;border-radius:6px;border-left:3px solid #C9A84C;'>"
            . nl2br(htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8')) . "</p>
          </td>
        </tr>" : '';

$corpo = '<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<style>
  body{font-family:Arial,Helvetica,sans-serif;background:#f0f0f0;margin:0;padding:20px;}
  .wrap{max-width:600px;margin:0 auto;background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.1);}
  .header{background:#0D0D0D;padding:30px 36px;}
  .header h1{color:#C9A84C;margin:0;font-size:20px;letter-spacing:3px;font-family:Georgia,serif;}
  .header p{color:#777;margin:6px 0 0;font-size:12px;letter-spacing:1px;}
  .body{padding:32px 36px;}
  table{width:100%;border-collapse:collapse;}
  td{padding:12px 0;border-bottom:1px solid #eee;vertical-align:top;}
  .label{color:#888;font-size:12px;text-transform:uppercase;letter-spacing:1.5px;font-weight:700;width:38%;padding-right:16px;}
  .value{color:#111;font-size:15px;font-weight:500;}
  .footer{background:#f9f9f9;padding:18px 36px;border-top:1px solid #eee;}
  .footer p{margin:0;font-size:11px;color:#aaa;}
  .badge{display:inline-block;background:#C9A84C;color:#0D0D0D;padding:4px 12px;border-radius:20px;font-size:12px;font-weight:700;letter-spacing:1px;text-transform:uppercase;margin-bottom:20px;}
</style>
</head>
<body>
<div class="wrap">
  <div class="header">
    <h1>CONSTRUTORA TAURUS</h1>
    <p>NOVO CONTATO VIA LANDING PAGE</p>
  </div>
  <div class="body">
    <p class="badge">Solicitação de Orçamento</p>
    <table>
      <tr><td class="label">Nome</td><td class="value">' . htmlspecialchars($nome, ENT_QUOTES, 'UTF-8') . '</td></tr>
      <tr><td class="label">Telefone</td><td class="value"><a href="tel:' . preg_replace('/\D/', '', $telefone) . '" style="color:#C9A84C;">' . htmlspecialchars($telefone, ENT_QUOTES, 'UTF-8') . '</a></td></tr>'
      . $linha_email . '
      <tr><td class="label">Tipo de Projeto</td><td class="value">' . htmlspecialchars(ucfirst($tipo), ENT_QUOTES, 'UTF-8') . '</td></tr>'
      . $linha_mensagem . '
    </table>
  </div>
  <div class="footer">
    <p>Enviado em ' . date('d/m/Y \à\s H:i') . ' · IP: ' . substr($ip_hash, 0, 8) . '...</p>
  </div>
</div>
</body>
</html>';

// --- Headers do e-mail ------------------------------------------
$headers  = "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: text/html; charset=UTF-8\r\n";
$headers .= "From: =?UTF-8?B?" . base64_encode($remetente_nome) . "?= <{$remetente_mail}>\r\n";
if (!empty($email_safe)) {
    $headers .= "Reply-To: {$email_safe}\r\n";
}
$headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
$headers .= "X-Priority: 1\r\n";

// --- Envio ------------------------------------------------------
$enviado = mail($destinatario, $assunto, $corpo, $headers);

if ($enviado) {
    echo json_encode(['success' => true, 'message' => 'Mensagem enviada com sucesso!']);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Falha ao enviar. Tente pelo WhatsApp.']);
}
