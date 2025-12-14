<?php

class LaundryOrder {
    private $conn;

    public $order_id = ""; 
    public $customer_id = "";
    public $customer_name_at_order = "";
    public $weight_lbs = 0.0; 
    public $service_type = "";
    public $status = "Pending";
    public $total_amount = 0.00;
    public $date_created = "";
    public $detergent_type = "Standard"; 
    public $softener_type = "Standard";
    public $starch_level = "None";
    public $special_instructions = ""; 
    public $defect_log = "";

    public function __construct(PDO $pdo_connection) {
        $this->conn = $pdo_connection;
    }

    public function createOrder() {
        try {
            $this->conn->exec("SET FOREIGN_KEY_CHECKS=0");
            $this->conn->beginTransaction();

            $max_attempts = 10;
            $attempt = 0;
            $this->date_created = date("Y-m-d H:i:s");

            // Unique ID generation loop
            do {
                $id_prefix = "ORD-" . date("ymd") . "-";
                $random_suffix = str_pad(random_int(10000, 99999), 5, '0', STR_PAD_LEFT); 
                $this->order_id = $id_prefix . $random_suffix;

                $sql_check = "SELECT COUNT(order_id) FROM laundry_order WHERE order_id = ?";
                $stmt = $this->conn->prepare($sql_check);
                $stmt->execute([$this->order_id]);
                $count = $stmt->fetchColumn();

                if ($count == 0) break;
                $attempt++;
                if ($attempt >= $max_attempts) {
                    $this->conn->rollBack();
                    $this->conn->exec("SET FOREIGN_KEY_CHECKS=1");
                    error_log("Failed to generate unique Order ID after {$max_attempts} attempts");
                    return false;
                }
            } while (true);

            // 1. Insert into Parent Table: laundry_order
            $sql = "INSERT INTO laundry_order (
                        order_id, 
                        customer_id, 
                        customer_name_at_order,
                        weight_lbs, 
                        service_type, 
                        status, 
                        total_amount,
                        date_created
                    ) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $this->conn->prepare($sql);
            $result = $stmt->execute([
                $this->order_id,
                $this->customer_id,
                $this->customer_name_at_order,
                $this->weight_lbs,
                $this->service_type,
                $this->status,
                $this->total_amount,
                $this->date_created
            ]);
            
            if (!$result) {
                $this->conn->rollBack();
                $this->conn->exec("SET FOREIGN_KEY_CHECKS=1");
                error_log("ERROR: Failed to insert laundry_order. Error: " . json_encode($stmt->errorInfo()) . " | Order ID: " . $this->order_id);
                return false;
            }
            error_log("SUCCESS: laundry_order inserted with order_id: " . $this->order_id);

            // 2. Insert into Child Table: order_details
            $sql_details = "INSERT INTO order_details (
                                order_id,
                                detergent_type,
                                softener_type,
                                starch_level,
                                special_instructions,
                                defect_log
                            ) 
                            VALUES (?, ?, ?, ?, ?, ?)";
            $stmt_details = $this->conn->prepare($sql_details);
            
            // Log the data being inserted
            error_log("DEBUG: Inserting order_details with values: " . json_encode([
                'order_id' => $this->order_id,
                'detergent_type' => $this->detergent_type,
                'softener_type' => $this->softener_type,
                'starch_level' => $this->starch_level,
                'special_instructions' => $this->special_instructions,
                'defect_log' => $this->defect_log
            ]));
            
            $result_details = $stmt_details->execute([
                $this->order_id,
                $this->detergent_type,
                $this->softener_type,
                $this->starch_level,
                $this->special_instructions,
                $this->defect_log
            ]);
            if (!$result_details) {
                $this->conn->rollBack();
                $this->conn->exec("SET FOREIGN_KEY_CHECKS=1");
                error_log("ERROR: Failed to insert order_details. Error: " . json_encode($stmt_details->errorInfo()));
                return false;
            }
            error_log("SUCCESS: order_details inserted for order_id: " . $this->order_id);

            $this->conn->commit();
            $this->conn->exec("SET FOREIGN_KEY_CHECKS=1");
            return true;
        } catch (PDOException $e) {
            $this->conn->rollBack();
            $this->conn->exec("SET FOREIGN_KEY_CHECKS=1");
            error_log("Order creation failed: " . $e->getMessage());
            return false;
        }
    }

    public function updateOrder() {
        try {
            $sql = "UPDATE laundry_order SET 
                        weight_lbs = ?, 
                        service_type = ?, 
                        status = ?,
                        total_amount = ?
                    WHERE order_id = ?";
            $stmt = $this->conn->prepare($sql);
            $result = $stmt->execute([
                $this->weight_lbs,
                $this->service_type,
                $this->status,
                $this->total_amount,
                $this->order_id
            ]);
            if ($result) {
                error_log("DEBUG: updateOrder executed for order_id: " . $this->order_id . " | affectedRows: " . $stmt->rowCount());
            } else {
                error_log("ERROR: updateOrder failed - " . json_encode($stmt->errorInfo()));
            }
            return $result;
        } catch (PDOException $e) {
            error_log("Order update failed: " . $e->getMessage());
            return false;
        }
    }

    public function updateOrderDetails() {
        try {
            $sql = "UPDATE order_details SET 
                        detergent_type = ?,
                        softener_type = ?,
                        starch_level = ?,
                        special_instructions = ?,
                        defect_log = ?
                    WHERE order_id = ?";
            
            // Log the data being updated
            error_log("DEBUG: updateOrderDetails - Updating order_id: " . $this->order_id);
            error_log("DEBUG: updateOrderDetails - Values: " . json_encode([
                'detergent_type' => $this->detergent_type,
                'softener_type' => $this->softener_type,
                'starch_level' => $this->starch_level,
                'special_instructions' => $this->special_instructions,
                'defect_log' => $this->defect_log
            ]));
            
            $stmt = $this->conn->prepare($sql);
            $result = $stmt->execute([
                $this->detergent_type,
                $this->softener_type,
                $this->starch_level,
                $this->special_instructions,
                $this->defect_log,
                $this->order_id
            ]);
            
            if (!$result) {
                error_log("ERROR: updateOrderDetails failed - Error: " . json_encode($stmt->errorInfo()));
                return false;
            }
            
            error_log("SUCCESS: order_details updated for order_id: " . $this->order_id);
            return $result;
        } catch (PDOException $e) {
            error_log("Order details update failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update both laundry_order and order_details within a transaction.
     * If order_details row does not exist, use an upsert pattern to create it.
     */
    public function updateOrderWithDetails() {
        try {
            $this->conn->beginTransaction();

            $sql_parent = "UPDATE laundry_order SET 
                        weight_lbs = ?, 
                        service_type = ?, 
                        status = ?,
                        total_amount = ?
                    WHERE order_id = ?";
            $stmt_parent = $this->conn->prepare($sql_parent);
            $parentResult = $stmt_parent->execute([
                $this->weight_lbs,
                $this->service_type,
                $this->status,
                $this->total_amount,
                $this->order_id
            ]);

            if (!$parentResult) {
                error_log("ERROR: updateOrderWithDetails - Parent update failed: " . json_encode($stmt_parent->errorInfo()));
                $this->conn->rollBack();
                return false;
            }

            // Upsert using INSERT ... ON DUPLICATE KEY UPDATE to ensure the details row exists
            $sql_details_upsert = "INSERT INTO order_details (
                                order_id,
                                detergent_type,
                                softener_type,
                                starch_level,
                                special_instructions,
                                defect_log
                            ) VALUES (?, ?, ?, ?, ?, ?)
                            ON DUPLICATE KEY UPDATE
                                detergent_type = VALUES(detergent_type),
                                softener_type = VALUES(softener_type),
                                starch_level = VALUES(starch_level),
                                special_instructions = VALUES(special_instructions),
                                defect_log = VALUES(defect_log)";

            $stmt_details = $this->conn->prepare($sql_details_upsert);
            $detailsResult = $stmt_details->execute([
                $this->order_id,
                $this->detergent_type,
                $this->softener_type,
                $this->starch_level,
                $this->special_instructions,
                $this->defect_log
            ]);

            if (!$detailsResult) {
                error_log("ERROR: updateOrderWithDetails - Details upsert failed: " . json_encode($stmt_details->errorInfo()));
                $this->conn->rollBack();
                return false;
            }

            $this->conn->commit();
            error_log("SUCCESS: updateOrderWithDetails committed for order_id: " . $this->order_id);
            return true;

        } catch (PDOException $e) {
            $this->conn->rollBack();
            error_log("updateOrderWithDetails transaction failed: " . $e->getMessage());
            return false;
        }
    }

    public function deleteOrder($order_id) {
        try {
            $sql_details = "DELETE FROM order_details WHERE order_id = ?";
            $stmt = $this->conn->prepare($sql_details);
            $stmt->execute([$order_id]);
            $sql = "DELETE FROM laundry_order WHERE order_id = ?";
            $stmt = $this->conn->prepare($sql);
            $result = $stmt->execute([$order_id]);
            return $result;
        } catch (PDOException $e) {
            error_log("Order deletion failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Fetches a single order by order_id, including order_details,
     * and populates the current object's properties.
     */
    public function fetchOrder($order_id) {
        try {
            $sql = "SELECT 
                        lo.*, 
                        od.detergent_type,
                        od.softener_type,
                        od.starch_level,
                        od.special_instructions,
                        od.defect_log,
                        c.name AS customer_name,
                        c.phone_number
                    FROM laundry_order lo
                    LEFT JOIN order_details od ON lo.order_id = od.order_id
                    LEFT JOIN customer c ON lo.customer_id = c.id
                    WHERE lo.order_id = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$order_id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC); 
            
            // Assign fetched data to object properties
            if ($row) {
                // Primary order fields
                $this->order_id = $row['order_id']; 
                $this->customer_id = $row['customer_id'];
                $this->customer_name_at_order = $row['customer_name_at_order'];
                $this->weight_lbs = $row['weight_lbs'];
                $this->service_type = $row['service_type'];
                $this->status = $row['status'];
                $this->total_amount = $row['total_amount'];
                $this->date_created = $row['date_created'];
                
                // Washing preferences and other details (from order_details table)
                $this->detergent_type = $row['detergent_type']; 
                $this->softener_type = $row['softener_type'];
                $this->starch_level = $row['starch_level'];
                $this->special_instructions = $row['special_instructions']; 
                $this->defect_log = $row['defect_log'];
            }
            
            return $row;
        } catch (PDOException $e) {
            error_log("Fetch order failed: " . $e->getMessage());
            return null;
        }
    }

    public function fetchOrderHistory($customer_id) {
        try {
            $sql = "SELECT 
                        lo.*,
                        od.detergent_type,
                        od.softener_type,
                        od.starch_level
                    FROM laundry_order lo
                    LEFT JOIN order_details od ON lo.order_id = od.order_id
                    WHERE lo.customer_id = ?
                    ORDER BY lo.date_created DESC";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$customer_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Fetch order history failed: " . $e->getMessage());
            return [];
        }
    }

    public function viewAllOrders($search = "", $month = null, $year = null) {
        try {
            $sql = "SELECT 
                        lo.*, 
                        od.detergent_type,
                        od.softener_type,
                        c.name AS customer_name,
                        c.phone_number
                    FROM laundry_order lo
                    LEFT JOIN order_details od ON lo.order_id = od.order_id
                    LEFT JOIN customer c ON lo.customer_id = c.id
                    WHERE (lo.order_id LIKE ?
                        OR lo.customer_name_at_order LIKE ?
                        OR c.name LIKE ?
                        OR c.phone_number LIKE ?)";
            $params = [
                "%{$search}%",
                "%{$search}%",
                "%{$search}%",
                "%{$search}%"
            ];
            if ($month && is_numeric($month) && $month > 0 && $month <= 12) {
                $sql .= " AND MONTH(lo.date_created) = ?";
                $params[] = $month;
            }
            if ($year && is_numeric($year) && $year > 0) {
                $sql .= " AND YEAR(lo.date_created) = ?";
                $params[] = $year;
            }
            $sql .= " ORDER BY lo.date_created DESC";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("View all orders failed: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get count of orders grouped by service type.
     */
    public function getServiceTypeAnalysis() {
        try {
            $sql = "SELECT service_type, COUNT(id) as total_orders 
                    FROM laundry_order 
                    GROUP BY service_type 
                    ORDER BY total_orders DESC";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Service type analysis failed: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get order volume analysis by time period.
     */
    public function getOrderVolumeAnalysis($period_sql_fragment, $period_name) {
        try {
            $sql = "SELECT {$period_sql_fragment} as {$period_name}, COUNT(id) as total_orders 
                    FROM laundry_order 
                    GROUP BY {$period_name} 
                    ORDER BY {$period_name} DESC";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Order volume analysis failed: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get revenue analysis by time period.
     */
    public function getRevenueAnalysis($period_sql_fragment, $period_name) {
        try {
            $sql = "SELECT 
                        {$period_sql_fragment} as {$period_name}, 
                        SUM(total_amount) as total_revenue 
                    FROM laundry_order 
                    WHERE status = 'Completed' AND total_amount > 0
                    GROUP BY {$period_name} 
                    ORDER BY {$period_name} DESC";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Revenue analysis failed: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get revenue summary for completed orders.
     */
    public function getRevenueSummary() {
        try {
            $sql = "SELECT 
                        SUM(total_amount) as total_revenue, 
                        COUNT(id) as total_completed_orders 
                    FROM laundry_order 
                    WHERE status = 'Completed'";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ?: ['total_revenue' => 0.00, 'total_completed_orders' => 0];
        } catch (PDOException $e) {
            error_log("Revenue summary failed: " . $e->getMessage());
            return ['total_revenue' => 0.00, 'total_completed_orders' => 0];
        }
    }

    /**
     * Count total orders.
     */
    public function countAllOrders() {
        try {
            $sql = "SELECT COUNT(id) as total FROM laundry_order";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['total'] ?? 0;
        } catch (PDOException $e) {
            error_log("Count orders failed: " . $e->getMessage());
            return 0;
        }
    }
}