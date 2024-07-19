#How to use:

$items = 
[
    [
        'name' => 'Item 1',
        'price' => '10.00',
        'quantity' => 1
    ],
    [
        'name' => 'Item 2',
        'price' => '5.00',
        'quantity' => 2
    ]
];

$clientId = 'AYOjBBa1DVAzyPWU5wQqImLPPfarEKPsEnXHPEyNEbgxPMX8zuVqqTLkQe_CBJRE-EzWS59QWdmYlDlc';
$clientSecret = 'EIxwxlsxQ_wHkNkxdkv7UJG8EY91-BjiG2qT7T6DkeYRMXT8gHLVT9KgAblbxLEYEQPCPVUQhGjhELpb';

// goto paypal
$paypalRestAPI = new paypalRestAPI($clientId, $clientSecret, true);
$paypalRestAPI->setReturnUrl('http://localhost:8000/payment/success');
$paypalRestAPI->setCancelUrl('http://localhost:8000/payment/faild');
$paypal_checkout = $paypalRestAPI->doCheckout($items);
if(!empty($paypal_checkout)) {
    header('Location:'.$paypal_checkout['payUrl']);
    exit;
}

// feedback
$paypalRestAPI = new paypalRestAPI($clientId, $clientSecret, true);
if($payment_result = $paypalRestAPI->doConfirm($status)) {
    dump($payment_result);
}
