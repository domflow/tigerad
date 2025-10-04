<?php
require_once 'geofence_config.php';

class GeofencePaymentService {
    private $db;
    
    public function __construct() {
        $this->db = getDB();
    }
    
    // Process credit purchase (Stripe integration stub)
    public function purchaseCredits($storeId, $packageId, $paymentMethod, $paymentToken) {
        try {
            // Get credit package details
            $stmt = $this->db->prepare("SELECT * FROM credit_packages WHERE id = ? AND is_active = TRUE");
            $stmt->execute([$packageId]);
            $package = $stmt->fetch();
            
            if (!$package) {
                return ['success' => false, 'error' => 'Invalid credit package'];
            }
            
            // Verify store ownership
            $stmt = $this->db->prepare("SELECT id FROM stores WHERE id = ? AND owner_id = (SELECT store_owner_id FROM api_tokens WHERE token = ?)");
            $stmt->execute([$storeId, $paymentToken]); // In real implementation, use proper auth
            $store = $stmt->fetch();
            
            if (!$store) {
                return ['success' => false, 'error' => 'Invalid store or unauthorized'];
            }
            
            // Process payment based on method
            $paymentResult = $this->processPayment($package['price'], $paymentMethod, $paymentToken);
            
            if (!$paymentResult['success']) {
                return ['success' => false, 'error' => $paymentResult['error']];
            }
            
            // Record credit purchase
            $stmt = $this->db->prepare("
                INSERT INTO credit_transactions (store_id, transaction_type, credits, amount, payment_method, payment_reference, status) 
                VALUES (?, 'purchase', ?, ?, ?, ?, 'completed')
            ");
            $stmt->execute([
                $storeId,
                $package['credits'],
                $package['price'],
                $paymentMethod,
                $paymentResult['transaction_id']
            ]);
            
            $transactionId = $this->db->lastInsertId();
            
            // Update credit balance
            $stmt = $this->db->prepare("
                INSERT INTO credit_balances (store_id, total_credits, available_credits) 
                VALUES (?, ?, ?) 
                ON DUPLICATE KEY UPDATE 
                total_credits = total_credits + VALUES(total_credits),
                available_credits = available_credits + VALUES(available_credits)
            ");
            $stmt->execute([
                $storeId,
                $package['credits'],
                $package['credits']
            ]);
            
            // Record payment log
            $this->logPayment($transactionId, $paymentMethod, $package['price'], 'completed', $paymentResult);
            
            return [
                'success' => true,
                'transaction_id' => $transactionId,
                'credits_purchased' => $package['credits'],
                'amount_paid' => $package['price']
            ];
            
        } catch (PDOException $e) {
            error_log("Purchase credits error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Purchase failed'];
        }
    }
    
    // Process refund for unused credits
    public function refundCredits($storeId, $creditsToRefund, $refundReason) {
        try {
            // Verify store has sufficient available credits
            $stmt = $this->db->prepare("SELECT available_credits FROM credit_balances WHERE store_id = ?");
            $stmt->execute([$storeId]);
            $balance = $stmt->fetch();
            
            if (!$balance || $balance['available_credits'] < $creditsToRefund) {
                return ['success' => false, 'error' => 'Insufficient credits for refund'];
            }
            
            // Calculate refund amount based on credit package pricing
            $refundAmount = $this->calculateRefundAmount($creditsToRefund);
            
            if ($refundAmount <= 0) {
                return ['success' => false, 'error' => 'Refund amount too small'];
            }
            
            // Process refund payment
            $refundResult = $this->processRefund($storeId, $refundAmount);
            
            if (!$refundResult['success']) {
                return ['success' => false, 'error' => $refundResult['error']];
            }
            
            // Update credit balance
            $stmt = $this->db->prepare("
                UPDATE credit_balances 
                SET available_credits = available_credits - ?, 
                    pending_refund_credits = pending_refund_credits + ? 
                WHERE store_id = ?
            ");
            $stmt->execute([$creditsToRefund, $creditsToRefund, $storeId]);
            
            // Record refund transaction
            $stmt = $this->db->prepare("
                INSERT INTO credit_transactions (store_id, transaction_type, credits, amount, refund_reason, status) 
                VALUES (?, 'refund', ?, ?, ?, 'completed')
            ");
            $stmt->execute([$storeId, -$creditsToRefund, -$refundAmount, $refundReason]);
            
            $transactionId = $this->db->lastInsertId();
            
            // Record payment log
            $this->logPayment($transactionId, 'refund', -$refundAmount, 'completed', $refundResult);
            
            return [
                'success' => true,
                'transaction_id' => $transactionId,
                'credits_refunded' => $creditsToRefund,
                'refund_amount' => $refundAmount
            ];
            
        } catch (PDOException $e) {
            error_log("Refund credits error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Refund failed'];
        }
    }
    
    // Process payment (Stripe integration stub)
    private function processPayment($amount, $paymentMethod, $paymentToken) {
        // This is a stub - actual implementation would integrate with Stripe/Google Wallet
        try {
            if ($paymentMethod === 'stripe') {
                // Stripe payment processing would go here
                // \Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);
                // $charge = \Stripe\Charge::create([
                //     'amount' => $amount * 100, // Convert to cents
                //     'currency' => PAYMENT_CURRENCY,
                //     'source' => $paymentToken,
                //     'description' => 'Geofence Ads Credit Purchase'
                // ]);
                // return ['success' => true, 'transaction_id' => $charge->id];
                
                // For now, return mock success
                return [
                    'success' => true,
                    'transaction_id' => 'stripe_mock_' . uniqid(),
                    'gateway_response' => ['status' => 'succeeded']
                ];
            } elseif ($paymentMethod === 'google_wallet') {
                // Google Wallet processing would go here
                return [
                    'success' => true,
                    'transaction_id' => 'google_mock_' . uniqid(),
                    'gateway_response' => ['status' => 'succeeded']
                ];
            }
            
            return ['success' => false, 'error' => 'Unsupported payment method'];
            
        } catch (Exception $e) {
            error_log("Payment processing error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Payment processing failed'];
        }
    }
    
    // Process refund
    private function processRefund($storeId, $refundAmount) {
        // This would integrate with payment gateway refund APIs
        try {
            // Mock refund processing
            return [
                'success' => true,
                'refund_id' => 'refund_mock_' . uniqid(),
                'gateway_response' => ['status' => 'succeeded']
            ];
        } catch (Exception $e) {
            error_log("Refund processing error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Refund processing failed'];
        }
    }
    
    // Calculate refund amount based on credits
    private function calculateRefundAmount($credits) {
        // Get the effective price per credit based on most recent purchase
        try {
            $stmt = $this->db->prepare("
                SELECT amount / credits as price_per_credit
                FROM credit_transactions
                WHERE store_id = ? AND transaction_type = 'purchase' AND status = 'completed'
                ORDER BY created_at DESC
                LIMIT 1
            ");
            $stmt->execute([$storeId]);
            $result = $stmt->fetch();
            
            $pricePerCredit = $result ? $result['price_per_credit'] : 1.0; // Default to $1 per credit
            return round($credits * $pricePerCredit, 2);
        } catch (PDOException $e) {
            error_log("Calculate refund amount error: " . $e->getMessage());
            return round($credits * 1.0, 2); // Default to $1 per credit
        }
    }
    
    // Log payment transaction
    private function logPayment($transactionId, $paymentMethod, $amount, $status, $gatewayResponse) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO payment_logs (transaction_id, store_id, payment_method, amount, currency, status, gateway_response) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            // Note: In real implementation, store_id would be passed as parameter
            $stmt->execute([
                $transactionId,
                null, // store_id would be passed
                $paymentMethod,
                $amount,
                PAYMENT_CURRENCY,
                $status,
                json_encode($gatewayResponse)
            ]);
            
            return true;
        } catch (PDOException $e) {
            error_log("Log payment error: " . $e->getMessage());
            return false;
        }
    }
    
    // Get payment history for store
    public function getPaymentHistory($storeId, $limit = 50, $offset = 0) {
        try {
            $sql = "
                SELECT pl.*, ct.credits, ct.transaction_type
                FROM payment_logs pl
                JOIN credit_transactions ct ON pl.transaction_id = ct.id
                WHERE pl.store_id = ?
                ORDER BY pl.created_at DESC
                LIMIT ? OFFSET ?
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$storeId, $limit, $offset]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Get payment history error: " . $e->getMessage());
            return [];
        }
    }
    
    // Get store credit balance
    public function getCreditBalance($storeId) {
        try {
            $stmt = $this->db->prepare("
                SELECT total_credits, available_credits, used_credits, pending_refund_credits
                FROM credit_balances
                WHERE store_id = ?
            ");
            $stmt->execute([$storeId]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Get credit balance error: " . $e->getMessage());
            return null;
        }
    }
    
    // Get credit transaction history
    public function getCreditHistory($storeId, $limit = 100, $offset = 0) {
        try {
            $sql = "
                SELECT ct.*, cp.name as package_name
                FROM credit_transactions ct
                LEFT JOIN credit_packages cp ON ct.credits = cp.credits AND ct.amount = cp.price
                WHERE ct.store_id = ?
                ORDER BY ct.created_at DESC
                LIMIT ? OFFSET ?
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$storeId, $limit, $offset]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Get credit history error: " . $e->getMessage());
            return [];
        }
    }
    
    // Process expired credits
    public function processExpiredCredits() {
        try {
            $sql = "
                UPDATE credit_balances cb
                JOIN stores s ON cb.store_id = s.id
                JOIN credit_transactions ct ON ct.store_id = s.id
                SET cb.available_credits = 0
                WHERE ct.created_at < DATE_SUB(NOW(), INTERVAL " . CREDIT_EXPIRY_MONTHS . " MONTH)
                AND ct.transaction_type = 'purchase'
                AND ct.status = 'completed'
                AND cb.available_credits > 0
            ";
            
            $stmt = $this->db->prepare($sql);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Process expired credits error: " . $e->getMessage());
            return false;
        }
    }
    
    // Validate payment method
    public function validatePaymentMethod($paymentMethod) {
        $validMethods = ['stripe', 'google_wallet'];
        return in_array($paymentMethod, $validMethods);
    }
    
    // Calculate transaction fees
    public function calculateTransactionFees($amount, $paymentMethod) {
        // Typical processing fees
        $fees = [
            'stripe' => 0.029, // 2.9%
            'google_wallet' => 0.025 // 2.5%
        ];
        
        $feeRate = $fees[$paymentMethod] ?? 0.03; // Default 3%
        $transactionFee = $amount * $feeRate;
        $fixedFee = 0.30; // Typical fixed fee
        
        return [
            'transaction_fee' => $transactionFee + $fixedFee,
            'net_amount' => $amount - ($transactionFee + $fixedFee)
        ];
    }
}
?>