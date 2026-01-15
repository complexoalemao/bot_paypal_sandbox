<?php
// ================================
// DEBUG
// ================================
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ================================
// CONFIG
// ================================
$botToken = "7738664119:AAF9OOZhzpdZbt8-OZ0lZLC4yZgIhat_cEo";
$clientId = "AdffUCeEAXTKA8poWBnH2FcKtxKotqw3597I9hnDEUVZjIF1lD2NWUjbhoDNGAxJxSfBUUlAPGLjS82K";
$secret   = "ECSD_2TAkgcLvn_LubRrG0JERnuOQQ2c8sxuA3W0LZ_UCIZXcuQiRLnBFcj0p1zHykmdOtP0ER7JyzYF";
$baseUrl  = "https://api-m.sandbox.paypal.com";

// ================================
// RECEBER UPDATE
// ================================
$update = json_decode(file_get_contents("php://input"), true);
if (!$update) exit;

$message = $update["message"]["text"] ?? "";
$chatId  = $update["message"]["chat"]["id"] ?? null;

$callback = $update["callback_query"] ?? null;

// ================================
// FUNÇÃO TELEGRAM
// ================================
function sendMessage($chatId, $text, $keyboard = null) {
    global $botToken;

    $data = [
        "chat_id" => $chatId,
        "text" => $text,
        "parse_mode" => "HTML"
    ];

    if ($keyboard) {
        $data["reply_markup"] = json_encode($keyboard);
    }

    file_get_contents(
        "https://api.telegram.org/bot$botToken/sendMessage?" .
        http_build_query($data)
    );
}

// ================================
// /start
// ================================
if ($message === "/start") {
    sendMessage(
        $chatId,
        "🛒 <b>Bem-vindo!</b>\n\nProduto disponível:\n💳 <b>Produto Premium – $5</b>",
        [
            "inline_keyboard" => [
                [
                    ["text" => "💳 Comprar por $5", "callback_data" => "comprar_5"]
                ]
            ]
        ]
    );
    exit;
}

// ================================
// BOTÃO COMPRAR
// ================================
if ($callback && $callback["data"] === "comprar_5") {

    // TOKEN PAYPAL
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => "$baseUrl/v1/oauth2/token",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD => "$clientId:$secret",
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => "grant_type=client_credentials",
        CURLOPT_HTTPHEADER => [
            "Accept: application/json"
        ]
    ]);

    $tokenResp = json_decode(curl_exec($ch), true);
    curl_close($ch);

    if (!isset($tokenResp["access_token"])) {
        sendMessage($callback["message"]["chat"]["id"], "❌ Erro ao gerar pagamento.");
        exit;
    }

    $token = $tokenResp["access_token"];

    // ORDER
    $order = [
        "intent" => "CAPTURE",
        "purchase_units" => [[
            "amount" => [
                "currency_code" => "USD",
                "value" => "5.00"
            ]
        ]],
        "application_context" => [
            "brand_name" => "Loja Telegram",
            "user_action" => "PAY_NOW",
            "return_url" => "https://seusite.com/sucesso",
            "cancel_url" => "https://seusite.com/cancelado"
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
        CURLOPT_POSTFIELDS => json_encode($order)
    ]);

    $orderResp = json_decode(curl_exec($ch), true);
    curl_close($ch);

    if (!isset($orderResp["links"])) {
        sendMessage($callback["message"]["chat"]["id"], "❌ Erro ao criar pedido.");
        exit;
    }

    foreach ($orderResp["links"] as $link) {
        if ($link["rel"] === "approve") {
            sendMessage(
                $callback["message"]["chat"]["id"],
                "💳 <b>Pagamento criado</b>\n\nClique para pagar:\n" . $link["href"]
            );
            exit;
        }
    }
}
