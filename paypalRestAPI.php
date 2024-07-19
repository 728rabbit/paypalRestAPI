<?php
/*
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
*/
namespace App\Libs;

class paypalRestAPI {
    
    protected $_clientId = '';
    protected $_clientSecret = '';
    protected $_sandboxMode = false;
    protected $_error_message = '';
    
    protected $_currency = 'HKD';
    protected $_discount = 0;
    protected $_discount_description = 'Discount Amount';
    protected $_shipping = 0;
    protected $_tax = 0;
    
    protected $_return_url = '';
    protected $_cancel_url = '';
    protected $_end_point = 'https://api.paypal.com/v1';

    public function __construct($clientId, $clientSecret, $sandboxMode = false) {    
        $this->_clientId = $clientId;
        $this->_clientSecret = $clientSecret;
        $this->_sandboxMode = $sandboxMode;
        if(!empty($this->_sandboxMode)) {
            $this->_end_point = 'https://api.sandbox.paypal.com/v1';
        }
    }

    public function doAuth() {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->_end_point.'/oauth2/token');
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, $this->_clientId.':'.$this->_clientSecret);
        curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
        
        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            $this->_error_message = curl_error($ch);
            return false;
        }
        else {
            $response = json_decode($response, true);
            if(!empty($response['error'])) {
                $this->_error_message = (!empty($response['error_description']))?$response['error_description']:'';
                return false;
            }
        }
        curl_close($ch);
        
        return (!empty($response))?$response:false;
    }

    public function setCurrency($value) {
        $this->_currency = strtoupper($value);
        return $this;
    }
    
    public function setDiscount($value, $description = '') {
        $this->_discount = round((double)max(0, $value), 2);
        if(!empty($description)) {
            $this->_discount_description = $description;
        }
        return $this;
    }
    
    public function setShipping($value) {
        $this->_shipping = round((double)max(0, $value), 2);
        return $this;
    }
    
    public function setTax($value) {
        $this->_tax = round((double)max(0, $value), 2);
        return $this;
    }
    
    public function setReturnUrl($value) {
        $this->_return_url = (string)$value;
        return $this;
    }
    
    public function setCancelUrl($value) {
        $this->_cancel_url = (string)$value;
        return $this;
    }

    public function doCheckout($items = []) {
        if(!empty($items) && $auth_info = $this->doAuth()) {
            foreach ($items as $key => $item) {
                $items[$key]['currency'] = $this->_currency;
            }
            if(!empty($this->_discount) && $this->_discount > 0) {
                $items[] = 
                [
                    'name'      =>  $this->_discount_description,
                    'price'     =>  ($this->_discount*-1),
                    'quantity'  =>  1,
                    'currency'  =>  $this->_currency
                ];
            }
            $subtotal = 0;
            foreach ($items as $key => $item) {
                $subtotal += round(($item['price']*$item['quantity']), 2);
            }
            $subtotal = round($subtotal, 2);
            
            $payment = 
            [
                'intent'                    =>  'sale',
                'payer'                     =>  
                [
                    'payment_method'        =>  'paypal'
                ],
                'transactions'              =>  
                [
                    [
                        'amount'            => 
                        [
                            'total'         =>  round(($subtotal + ($this->_discount*-1)+ $this->_shipping + $this->_tax), 2),
                            'currency'      =>  $this->_currency,
                            'details'       => 
                            [
                                'subtotal'  =>  $subtotal,
                                'discount'  =>  $this->_discount,
                                'shipping'  =>  $this->_shipping,
                                'tax'       =>  $this->_tax,
                            ]
                        ],
                        'item_list'         =>      
                        [
                            'items'         =>  $items
                        ]
                    ]
                ],
                'redirect_urls'             =>
                [
                    'return_url'            =>  $this->_return_url,
                    'cancel_url'            =>  $this->_cancel_url
                ]
            ];
            
            $access_token = $auth_info['access_token'];
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->_end_point.'/payments/payment');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, 
            [
                'Content-Type: application/json',
                'Authorization: Bearer '.$access_token
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payment));
            
            $response = curl_exec($ch);
            if (curl_errno($ch)) {
                $this->_error_message = curl_error($ch);
                return false;
            }
            else {
                $response = json_decode($response, true);
                if(!empty($response['error'])) {
                    $this->_error_message = (!empty($response['error_description']))?$response['error_description']:'';
                    return false;
                }
            }
            curl_close($ch);
            
            $approvalUrl = '';
            if(!empty($response['links'])) {
                foreach ($response['links'] as $link) {
                    if (strtolower($link['rel']) == 'approval_url') {
                        $approvalUrl = $link['href'];
                        break;
                    }
                }
            }
            
            if(!empty($approvalUrl)) {
                return 
                [
                    'payID'     =>  $response['id'],
                    'payUrl'    =>  $approvalUrl
                ];
            }
        }
        
        return false;
    }
    
    public function doConfirm($status = '') {
        if (strtolower($status) == 'success' && $auth_info = $this->doAuth()) {
            $access_token = $auth_info['access_token'];
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->_end_point.'/payments/payment/'.($_GET['paymentId']).'/execute');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, 
            [
                'Content-Type: application/json',
                'Authorization: Bearer '.$access_token
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['payer_id' => $_GET['PayerID']]));
            
            $response = curl_exec($ch);
            if (curl_errno($ch)) {
                $this->_error_message = curl_error($ch);
                return false;
            }
            else {
                $response = json_decode($response, true);
                if(!empty($response['error'])) {
                    $this->_error_message = (!empty($response['error_description']))?$response['error_description']:'';
                    return false;
                }
            }
            curl_close($ch);
            
            return $response;
        }
        
        return false;
    }

    public function queryStatus($paymentId = '') {
        $paymentId = 'PAYMENT_ID_TO_QUERY';
        if(!empty($paymentId) && $auth_info = $this->doAuth()) {
            $access_token = $auth_info['access_token'];
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->_end_point.'/payments/payment/'.$paymentId);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, 
            [
                'Content-Type: application/json',
                'Authorization: Bearer '.$access_token
            ]);
            
            $response = curl_exec($ch);
            if (curl_errno($ch)) {
                $this->_error_message = curl_error($ch);
                return false;
            }
            else {
                $response = json_decode($response, true);
                if(!empty($response['error'])) {
                    $this->_error_message = (!empty($response['error_description']))?$response['error_description']:'';
                    return false;
                }
            }
            curl_close($ch);
            
            return $response;
        }
        
        return false;
    }

    public function getErrorMessage() {
        return $this->_error_message;
    }
}
