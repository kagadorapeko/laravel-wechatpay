<?php

namespace KagaDorapeko\Laravel\Wechatpay;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use OpenSSLAsymmetricKey;

class WechatpayService
{
    protected array $config;

    protected PendingRequest $apiClient;

    public function __construct()
    {
        $this->refreshConfig();
    }

    public function refreshConfig()
    {
        $this->config = config('wechatpay');

        $this->apiClient = Http::withOptions([
            'base_uri' => 'https://api.mch.weixin.qq.com'
        ])->withHeaders([
            'Accept' => 'application/json',
            'Content-Type' => 'application/json; charset=utf-8',
            'User-Agent' => 'wechatpay-php/1.4.3 GuzzleHttp/7 curl/7.80.0 (Linux/3.10.0-1160.49.1.el7.x86_64) PHP/8.0.15',
        ]);
    }

    public function handleAppPayment(int $amount, string $orderNo, string $callbackUrl): array|null
    {
        $merchantPrivateKey = $this->getMerchantPrivateKey();

        $response = $this->handleAppPaymentRequest($merchantPrivateKey, [
            'appid' => $this->config['app_appid'],
            'mchid' => $this->config['appid'],
            'description' => $orderNo,
            'out_trade_no' => $orderNo,
            'notify_url' => $callbackUrl,
            'amount' => [
                'total' => $amount,
                'currency' => 'CNY',
            ],
        ]);

        if (!$response) return null;

        $signData = [
            'appid' => $this->config['app_appid'],
            'timestamp' => (string)time(),
            'noncestr' => $this->getNonceStr(),
            'prepayid' => $response['prepay_id'],
        ];

        $signContent = implode("\n", $signData) . "\n";

        openssl_sign($signContent, $sign, $merchantPrivateKey, OPENSSL_ALGO_SHA256);

        return array_merge($signData, [
            'partnerid' => $this->config['appid'],
            'package' => 'Sign=WXPay',
            'prepayid' => $response['prepay_id'],
            'sign' => base64_encode($sign),
        ]);
    }

    public function handelNotifyPayment(Request $request): array|null
    {
        $requestSign = $request->header('Wechatpay-Signature');
        $requestNonceStr = $request->header('Wechatpay-Nonce');
        $requestTimestamp = $request->header('Wechatpay-Timestamp');

        if (!$requestSign or !$requestNonceStr or !$requestTimestamp) {
            return null;
        }

        $verifyData = [$requestTimestamp, $requestNonceStr, $request->getContent()];

        $verifyContent = implode("\n", $verifyData) . "\n";

        $platformPublicKey = $this->getPlatformPublicKey();

        $verified = openssl_verify(
            $verifyContent, base64_decode($requestSign),
            $platformPublicKey, OPENSSL_ALGO_SHA256
        );

        if ($verified !== 1) return null;

        $resource = $request->input('resource');
        $ciphertext = base64_decode($resource['ciphertext']);

        $plaintext = openssl_decrypt(
            substr($ciphertext, 0, -16), 'aes-256-gcm',
            $this->config['secret'], OPENSSL_RAW_DATA, $resource['nonce'],
            substr($ciphertext, -16), $resource['associated_data']
        );

        return json_decode($plaintext, true);
    }

    protected function handleAppPaymentRequest(OpenSSLAsymmetricKey $merchantPrivateKey, array $data): array|null
    {
        $response = $this->apiClient->withHeaders([
            'Authorization' => $this->getAppPaymentAuthorization(json_encode($data), $merchantPrivateKey),
        ])->post('/v3/pay/transactions/app', $data);

        if ($response->successful() and $result = $response->json()) {
            return $result;
        }

        return null;
    }

    protected function getNonceStr(): string
    {
        return str_replace('-', '', Str::uuid());
    }

    protected function getAppPaymentAuthorization(string $body, OpenSSLAsymmetricKey $merchantPrivateKey): string
    {
        $timestamp = (string)time();
        $nonceStr = $this->getNonceStr();
        $signContent = "POST\n/v3/pay/transactions/app\n$timestamp\n$nonceStr\n$body\n";

        openssl_sign($signContent, $sign, $merchantPrivateKey, OPENSSL_ALGO_SHA256);

        return 'WECHATPAY2-SHA256-RSA2048 ' . sprintf(
                'mchid="%s",nonce_str="%s",timestamp="%d",serial_no="%s",signature="%s"',
                $this->config['appid'], $nonceStr, $timestamp,
                $this->config['merchant_key_serial'],
                base64_encode($sign)
            );
    }

    protected function getMerchantPrivateKey(): OpenSSLAsymmetricKey
    {
        return openssl_pkey_get_private($this->config['merchant_key_pem']);
    }

    protected function getPlatformPublicKey(): OpenSSLAsymmetricKey
    {
        return openssl_pkey_get_public($this->config['public_cert_pem']);
    }
}