<?php
// ================================
// CONFIGURAÇÃO DO BOT
// ================================
$botToken = "7738664119:AAF9OOZhzpdZbt8-OZ0lZLC4yZgIhat_cEo";       // Token do BotFather
$chatId   = "8312325078";                     // Vai ser preenchido dinamicamente
$clientId = "AdffUCeEAXTKA8poWBnH2FcKtxKotqw3597I9hnDEUVZjIF1lD2NWUjbhoDNGAxJxSfBUUlAPGLjS82K";
$secret   = "ECSD_2TAkgcLvn_LubRrG0JERnuOQQ2c8sxuA3W0LZ_UCIZXcuQiRLnBFcj0p1zHykmdOtP0ER7JyzYF";
$baseUrl  = "https://api-m.sandbox.paypal.com";

// ================================
// LER UPDATE DO TELEGRAM
// ================================
$update = json_decode(file_get_contents("php://input"), true);
if (!$update) exit;

$message = $update["message"]["text"] ?? "";
$chatId  = $update["message"]["chat"]["id"] ?? null;

if (!$message || !$chatId) exit;

// ================================
// TRATAR COMANDO /card
// ================================
if (strpos($message, "/card") === 0) {
    // remover o comando e pegar o restante
    $cardsList = trim(str_replace("/card", "", $message));
    $cards = array_filter(array_map('trim', explode("\n", $cardsList)));

    if (count($cards) === 0) {
        sendMessage($chatId, "Envie pelo menos 1 cartão no formato: 4066698784649380|07|2028|847");
        exit;
    }

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
        sendMessage($chatId, "Não foi possível gerar token PayPal.");
        exit;
    }

    // ================================
    // 2️⃣ CRIAR ORDER PAYPAL
    // ================================
    // Valor: 1 USD por cartão (simulação)
    $amount = count($cards); // por exemplo 1 USD por cartão
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
        sendMessage($chatId, "Erro ao criar order PayPal:\n$orderRaw");
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
        sendMessage($chatId, "Link de aprovação não encontrado.");
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
