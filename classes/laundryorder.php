<?php

class LaundryOrder {
    private $conn;
    private $table = "laundry_order"; // <--- ADDED THIS MISSING PROPERTY

    public $order_id = ""; 
    public $customer_id = "";
    public $customer_name_at_order = "";
    public $customer_phone_at_order = "";
    public $customer_email = ""; 
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

                $sql_check = "SELECT COUNT(order_id) FROM " . $this->table . " WHERE order_id = ?";
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
            $sql = "INSERT INTO " . $this->table . " (
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
                return false;
            }

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
                return false;
            }
            
            // 3. Insert Snapshot
            $sql_snapshot = "INSERT INTO order_customer_snapshot (order_id, snapshot_name, snapshot_phone) VALUES (?, ?, ?)";
            $stmt_snapshot = $this->conn->prepare($sql_snapshot);
            $result_snapshot = $stmt_snapshot->execute([
                $this->order_id,
                $this->customer_name_at_order,
                $this->customer_phone_at_order 
            ]);

            if(!$result_snapshot) {
                $this->conn->rollBack();
                $this->conn->exec("SET FOREIGN_KEY_CHECKS=1");
                return false;
            }

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

    public function updateOrderWithDetails() {
        try {
            $this->conn->beginTransaction();

            $sql_parent = "UPDATE " . $this->table . " SET 
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
                $this->conn->rollBack();
                return false;
            }

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
                $this->conn->rollBack();
                return false;
            }

            $this->conn->commit();
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
            $sql = "DELETE FROM " . $this->table . " WHERE order_id = ?";
            $stmt = $this->conn->prepare($sql);
            $result = $stmt->execute([$order_id]);
            return $result;
        } catch (PDOException $e) {
            error_log("Order deletion failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Fetches a single order by order_id, including order_details and EMAIL.
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
                        COALESCE(c.name, ocs.snapshot_name) AS customer_name,
                        COALESCE(c.phone_number, ocs.snapshot_phone) AS phone_number,
                        c.email AS customer_email
                    FROM " . $this->table . " lo
                    LEFT JOIN order_details od ON lo.order_id = od.order_id
                    LEFT JOIN customer c ON lo.customer_id = c.id
                    LEFT JOIN order_customer_snapshot ocs ON lo.order_id = ocs.order_id
                    WHERE lo.order_id = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$order_id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC); 
            
            if ($row) {
                $this->order_id = $row['order_id']; 
                $this->customer_id = $row['customer_id'];
                $this->customer_name_at_order = $row['customer_name_at_order'];
                $this->weight_lbs = $row['weight_lbs'];
                $this->service_type = $row['service_type'];
                $this->status = $row['status'];
                $this->total_amount = $row['total_amount'];
                $this->date_created = $row['date_created'];
                $this->customer_phone_at_order = $row['phone_number'];
                $this->customer_email = $row['customer_email'];
                
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

    public function viewAllOrders($search = "", $month = null, $year = null) {
        try {
            $sql = "SELECT 
                        lo.*, 
                        od.detergent_type,
                        od.softener_type,
                        COALESCE(c.name, lo.customer_name_at_order) AS customer_name,
                        COALESCE(c.phone_number, ocs.snapshot_phone) AS phone_number
                    FROM " . $this->table . " lo
                    LEFT JOIN order_details od ON lo.order_id = od.order_id
                    LEFT JOIN customer c ON lo.customer_id = c.id
                    LEFT JOIN order_customer_snapshot ocs ON lo.order_id = ocs.order_id
                    WHERE (lo.order_id LIKE ?
                        OR lo.customer_name_at_order LIKE ?
                        OR c.name LIKE ?
                        OR c.phone_number LIKE ?
                        OR ocs.snapshot_phone LIKE ?)";

            $params = ["%{$search}%", "%{$search}%", "%{$search}%", "%{$search}%", "%{$search}%"];
            
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

    public function getOrderVolumeAnalysis($date_format, $alias) {
        $sql = "SELECT $date_format as $alias, COUNT(id) as total_orders 
                FROM " . $this->table . " 
                GROUP BY $alias 
                ORDER BY $alias ASC";
        return $this->conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getRevenueAnalysis($date_format, $alias) {
        $sql = "SELECT $date_format as $alias, SUM(total_amount) as total_revenue 
                FROM " . $this->table . " 
                WHERE status = 'Completed' AND total_amount > 0
                GROUP BY $alias 
                ORDER BY $alias ASC";
        return $this->conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getServiceTypeAnalysis() {
        $sql = "SELECT service_type, COUNT(id) as total_orders 
                FROM " . $this->table . " 
                GROUP BY service_type 
                ORDER BY total_orders DESC";
        return $this->conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getRevenueSummary() {
        $sql = "SELECT SUM(total_amount) as total_revenue, COUNT(id) as total_completed_orders 
                FROM " . $this->table . " 
                WHERE status = 'Completed'";
        return $this->conn->query($sql)->fetch(PDO::FETCH_ASSOC);
    }
}
?>