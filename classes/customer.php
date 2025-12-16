<?php

class Customer {
    private $conn;
    private $table = 'customer';
    public $id;
    public $name;
    public $phone_number;
    public $email;
    public $date_registered;
    public $is_active;
    public $email_verification_token;
    public $email_verified_at;
    public $email_verification_sent_at;

    /**
     * Constructor: Dependency Injection for PDO connection
     * @param PDO $pdo_connection
     */
    public function __construct(PDO $pdo_connection) {
        $this->conn = $pdo_connection;
    }

    /**
     * Add a new customer
     * @return bool
     */
    public function addCustomer() {
        $sql = "INSERT INTO " . $this->table . " (name, phone_number, email, is_active, email_verification_token, email_verification_sent_at) 
            VALUES (:name, :phone_number, :email, :is_active, :email_verification_token, :email_verification_sent_at)";

        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                ':name' => $this->name,
                ':phone_number' => $this->phone_number,
                ':email' => $this->email ?? null,
                ':is_active' => $this->is_active ?? 1,
                ':email_verification_token' => $this->email_verification_token ?? null,
                ':email_verification_sent_at' => $this->email_verification_sent_at ?? null
            ]);
            return true;
        } catch (PDOException $e) {
            error_log("Customer add error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Fetch a single customer by ID or phone
     * @param mixed $identifier ID or phone number
     * @return array|false
     */
    public function fetchCustomer($identifier) {
        $sql = "SELECT * FROM " . $this->table . " 
                WHERE id = :id OR phone_number = :phone LIMIT 1";

        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                ':id' => is_numeric($identifier) ? $identifier : 0,
                ':phone' => $identifier
            ]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Customer fetch error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * View/search customers
     * @param string $search_term
     * @return array
     */
    public function viewCustomers($search_term = '') {
        $sql = "SELECT id, name, phone_number, email, date_registered, is_active, email_verified_at 
                FROM " . $this->table;

        if (!empty($search_term)) {
            $sql .= " WHERE name LIKE :search OR phone_number LIKE :search";
        }

        $sql .= " ORDER BY name ASC";

        try {
            $stmt = $this->conn->prepare($sql);
            if (!empty($search_term)) {
                $stmt->execute([':search' => '%' . $search_term . '%']);
            } else {
                $stmt->execute();
            }
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Customer view error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Check if customer exists by phone
     * @param string $phone_number
     * @param int $exclude_id Optional: exclude this ID from check
     * @return bool
     */
    public function isCustomerExist($phone_number, $exclude_id = null) {
        $sql = "SELECT COUNT(*) as count FROM " . $this->table . " 
                WHERE phone_number = :phone";

        $params = [':phone' => $phone_number];

        if ($exclude_id) {
            $sql .= " AND id != :id";
            $params[':id'] = $exclude_id;
        }

        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['count'] > 0;
        } catch (PDOException $e) {
            error_log("Customer exist check error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete a customer by ID
     * @param int $id
     * @return bool
     */
    public function deleteCustomer($id) {
        $sql = "DELETE FROM " . $this->table . " WHERE id = :id";

        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([':id' => $id]);
            return true;
        } catch (PDOException $e) {
            error_log("Customer delete error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get total customer count
     * @return int
     */
    public function countAll() {
        $sql = "SELECT COUNT(*) as count FROM " . $this->table;

        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return (int)$result['count'];
        } catch (PDOException $e) {
            error_log("Customer count error: " . $e->getMessage());
            return 0;
        }
    }

    /**
 * Check if a customer exists with the given email address.
 * @param string $email The email to check for uniqueness.
 * @param int $exclude_id Optional: exclude this ID from check (for update operations).
 * @return bool
 */
public function isEmailExist($email, $exclude_id = null) {
    // Only check for existence if the email is provided and not null/empty
    if (empty($email)) {
        return false;
    }
    
    $sql = "SELECT COUNT(*) as count FROM " . $this->table . " 
            WHERE email = :email";

    $params = [':email' => $email];

    if ($exclude_id) {
        $sql .= " AND id != :id";
        $params[':id'] = $exclude_id;
    }

    try {
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Returns true if count > 0 (meaning the email exists)
        return $result['count'] > 0;
    } catch (PDOException $e) {
        error_log("Email exist check error: " . $e->getMessage());
        return false;
    }
}

    /**
     * Generate and set a new verification token for a customer and return it.
     * @param int $customer_id
     * @return string|false
     */
    public function generateVerificationToken($customer_id) {
        $token = bin2hex(random_bytes(16));
        $sql = "UPDATE " . $this->table . " SET email_verification_token = :token, email_verification_sent_at = NOW() WHERE id = :id";
        try {
            $stmt = $this->conn->prepare($sql);
            $result = $stmt->execute([':token' => $token, ':id' => $customer_id]);
            if ($result) return $token;
            return false;
        } catch (PDOException $e) {
            error_log("Customer token update error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Verify a customer's email using a token.
     * @param string $token
     * @return bool
     */
    // --- ADD THIS TO classes/customer.php ---

    /**
     * Regenerate verification token for an existing customer
     */
    public function refreshVerificationToken($customer_id) {
        $token = bin2hex(random_bytes(16));
        $sql = "UPDATE " . $this->table . " 
                SET email_verification_token = :token, 
                    email_verification_sent_at = NOW() 
                WHERE id = :id";
        
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                ':token' => $token,
                ':id' => $customer_id
            ]);
            return $token; // Return the new token so we can email it
        } catch (PDOException $e) {
            error_log("Token Refresh Error: " . $e->getMessage());
            return false;
        }
    }
    public function verifyByToken($token): bool|string {
        // Check token validity and expiry
        try {
            $stmt = $this->conn->prepare("SELECT id, email_verification_sent_at FROM " . $this->table . " WHERE email_verification_token = :token LIMIT 1");
            $stmt->execute([':token' => $token]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) return false; // not found

            // If sent_at is null, treat as invalid
            if (empty($row['email_verification_sent_at'])) return false;

            $sent_at = new DateTime($row['email_verification_sent_at']);
            $now = new DateTime();
            // Token expiry: 48 hours
            $interval = $now->getTimestamp() - $sent_at->getTimestamp();
            if ($interval > 48 * 3600) {
                // Expired: clear token to prevent reuse
                $stmt2 = $this->conn->prepare("UPDATE " . $this->table . " SET email_verification_token = NULL, email_verification_sent_at = NULL WHERE id = :id");
                $stmt2->execute([':id' => $row['id']]);
                return 'expired';
            }

            // Valid: set verified_at and clear token
            $stmt3 = $this->conn->prepare("UPDATE " . $this->table . " SET email_verified_at = NOW(), email_verification_token = NULL, email_verification_sent_at = NULL WHERE id = :id");
            $stmt3->execute([':id' => $row['id']]);
            return $stmt3->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("Customer verify error: " . $e->getMessage());
            return false;
        }
    }
}