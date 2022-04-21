# Wechatpay SDK For Laravel

## 安装

通过 Composer 安装

```shell
$ composer require kagadorapeko/laravel-wechatpay
```

## 配置

在 `.env` 中添加参数

```dotenv
WECHATPAY_APPID="微信支付商户ID"
WECHATPAY_SECRET="微信支付商户密钥"
WECHATPAY_APP_APPID="微信支付应用ID"

WECHATPAY_PUBLIC_CERT_SERIAL="微信支付平台证书指纹"
WECHATPAY_MERCHANT_KEY_SERIAL="微信支付商户证书指纹"

WECHATPAY_PUBLIC_CERT_PEM="
-----BEGIN CERTIFICATE-----
微信支付平台证书
-----END CERTIFICATE-----
"

WECHATPAY_MERCHANT_KEY_PEM="
-----BEGIN PRIVATE KEY-----
微信支付商户证书
-----END PRIVATE KEY-----
"
```

## 使用

```php
// 价格：1元
$amount = 100;

// 订单号
$orderNo = '2022-03-22';

//回调链接
$callbackUrl = 'https://www.google.com/';

// 注入支付服务
$wechatpayService = app(\KagaDorapeko\Laravel\Wechatpay\WechatpayService::class);

// 获取支付凭证
$payload = $wechatpayService->handleAppPayment($amount, $orderNo, $callbackUrl);

// 注入请求
$request = app(\Illuminate\Http\Request::class);

// 支付回调验签并获取数据
if (!$response = $wechatpayService->handleNotifyPayment($request)) {
    throw new Exception('验签失败');
}

if ($response['trade_state'] !== 'SUCCESS') {
    throw new Exception('交易尚未成功，同志仍需努力');
}
```