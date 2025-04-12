
<?php
// Script to create sample logs for displaying processing time

include 'config.php';
include 'db_connect.php';
include 'log_helper.php';

try {
    // Get all orders that have status other than 'Pending'
    $stmt = $conn->prepare("
        SELECT id, status, user_id 
        FROM orders 
        WHERE status != 'Pending'
    ");
    
    $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Creating sample logs for " . count($orders) . " orders...\n";
    
    foreach ($orders as $order) {
        // Check if log already exists
        $check_stmt = $conn->prepare("
            SELECT COUNT(*) 
            FROM logs 
            WHERE action = 'order_status_update' 
            AND JSON_EXTRACT(details, '$.order_id') = :order_id
        ");
        $check_stmt->bindParam(':order_id', $order['id'], PDO::PARAM_INT);
        $check_stmt->execute();
        
        if ($check_stmt->fetchColumn() == 0) {
            // Create log
            log_order_action($conn, 1, 'order_status_update', $order['id'], [
                'order_id' => $order['id'],
                'old_status' => 'Pending',
                'new_status' => $order['status']
            ]);
            
            echo "Created log for order ID: " . $order['id'] . "\n";
        } else {
            echo "Log already exists for order ID: " . $order['id'] . "\n";
        }
    }
    
    echo "Done!\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
