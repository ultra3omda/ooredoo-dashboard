<?php

namespace App\Traits;

trait TransactionHelper
{
    protected function extractPricepointId($result): ?string
    {
        if (empty($result)) {
            return null;
        }
        
        try {
            $data = is_string($result) ? json_decode($result, true) : $result;
            if (!$data || !is_array($data)) {
                return null;
            }
            
            $fields = ['pricepointId', 'pricepoint_id', 'pricePointId', 'price_point_id', 'ppid', 'PPID'];
            
            foreach ($fields as $field) {
                if (isset($data[$field])) {
                    return (string)$data[$field];
                }
            }
            
            if (isset($data['user']['pricepointId'])) return (string)$data['user']['pricepointId'];
            if (isset($data['response']['pricepointId'])) return (string)$data['response']['pricepointId'];
            if (isset($data['data']['pricepointId'])) return (string)$data['data']['pricepointId'];
            
            return null;
        } catch (\Exception $e) {
            return null;
        }
    }
    
    protected function extractTotalCharged($result): float
    {
        if (empty($result)) {
            return 0.0;
        }
        
        try {
            $data = is_string($result) ? json_decode($result, true) : $result;
            if (!$data || !is_array($data)) {
                return 0.0;
            }
            
            if (isset($data['totalCharged']) && is_numeric($data['totalCharged'])) {
                return floatval($data['totalCharged']);
            }
            
            $variants = ['total_charged', 'totalCharged', 'totalChargedAmount', 'chargedAmount'];
            foreach ($variants as $variant) {
                if (isset($data[$variant]) && is_numeric($data[$variant])) {
                    return floatval($data[$variant]);
                }
            }
            
            return 0.0;
        } catch (\Exception $e) {
            return 0.0;
        }
    }
    
    protected function isTransactionDelivered($result): bool
    {
        if (empty($result)) {
            return false;
        }
        
        try {
            $resultString = is_string($result) ? $result : json_encode($result);
            
            if (stripos($resultString, '"mnoDeliveryCode":"DELIVERED"') !== false ||
                stripos($resultString, '"mnoDeliveryCode": "DELIVERED"') !== false ||
                stripos($resultString, '"mnoDeliveryCode":"delivered"') !== false ||
                stripos($resultString, '"mnoDeliveryCode": "delivered"') !== false) {
                return true;
            }
            
            $data = is_string($result) ? json_decode($result, true) : $result;
            if (!$data || !is_array($data)) {
                return false;
            }
            
            $deliveryCode = null;
            if (isset($data['mnoDeliveryCode'])) $deliveryCode = $data['mnoDeliveryCode'];
            elseif (isset($data['mno_delivery_code'])) $deliveryCode = $data['mno_delivery_code'];
            elseif (isset($data['response']['mnoDeliveryCode'])) $deliveryCode = $data['response']['mnoDeliveryCode'];
            elseif (isset($data['data']['mnoDeliveryCode'])) $deliveryCode = $data['data']['mnoDeliveryCode'];
            
            if ($deliveryCode && strtoupper(trim($deliveryCode)) === 'DELIVERED') {
                return true;
            }
            
            return false;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    protected function extractAmountFromResult($result): float
    {
        if (empty($result)) {
            return 0.0;
        }
        
        try {
            if (is_array($result)) {
                $data = $result;
            } elseif (is_string($result)) {
                $data = json_decode($result, true);
                if (!$data || json_last_error() !== JSON_ERROR_NONE) {
                    return 0.0;
                }
            } else {
                return 0.0;
            }
            
            $amountFields = [
                'amount', 'price', 'cost', 'value', 'total',
                'montant', 'prix', 'revenue', 'revenu',
                'charge_amount', 'billing_amount', 'transaction_amount',
                'totalCharged', 'total_charged'
            ];
            
            foreach ($amountFields as $field) {
                if (isset($data[$field]) && is_numeric($data[$field])) {
                    $amount = floatval($data[$field]);
                    if ($amount > 0) return $amount;
                }
            }
            
            $nestedPaths = [
                ['user', 'amount'], ['response', 'amount'], ['data', 'amount'],
                ['result', 'amount'], ['transaction', 'amount'], ['billing', 'amount'],
                ['charge', 'amount'], ['user', 'price'], ['response', 'price'],
                ['data', 'price'], ['user', 'total'], ['response', 'total'],
                ['data', 'total'], ['user', 'totalCharged'], ['response', 'totalCharged']
            ];
            
            foreach ($nestedPaths as $path) {
                $value = $data;
                foreach ($path as $key) {
                    if (!isset($value[$key])) { $value = null; break; }
                    $value = $value[$key];
                }
                if ($value !== null && is_numeric($value)) {
                    $amount = floatval($value);
                    if ($amount > 0) return $amount;
                }
            }
            
            return 0.0;
        } catch (\Exception $e) {
            return 0.0;
        }
    }
}
