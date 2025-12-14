<?php

require_once "database.php";

class Customer extends Database {
    public $id = "";
    public $name = "";
    public $phone_number = "";
    public $tableName = "customer"; // Added for clarity/consistency

    // Add a new customer to the database
    public function addCustomer() {
        $sql = "INSERT INTO " . $this->tableName . " (name, phone_number) VALUES (:name, :phone_number)";
        
        $query = $this->connect()->prepare($sql);

        $query->bindParam(":name", $this->name);
        $query->bindParam(":phone_number", $this->phone_number);

        return $query->execute();
    }

    // Checks if a customer already exists by phone number (for quick ID)
    public function isCustomerExist($phone_number, $id="") {
        $sql = "SELECT COUNT(*) as total FROM " . $this->tableName . " WHERE phone_number = :phone_number AND id <> :id";
        $query = $this->connect()->prepare($sql);
        $query->bindParam(":phone_number", $phone_number);
        $query->bindParam(":id", $id);

        $record = null;
        
        if ($query->execute()) {
            $record = $query->fetch();
        }

        return $record["total"] > 0;
    }

    
    // Fetches a single customer record by ID, Name, or Phone Number. (REVISED for Security)
    public function fetchCustomer($search_term) {
        // Use separate parameters for ID (int) vs. Name/Phone (string) checks
        $sql = "SELECT * FROM " . $this->tableName . " WHERE id = :id_term OR name LIKE CONCAT('%', :string_term, '%') OR phone_number = :string_term LIMIT 1";
        $query = $this->connect()->prepare($sql);
        
        // Determine the ID value to bind (0 if not numeric)
        $id_value = is_numeric($search_term) && $search_term > 0 ? $search_term : 0;
        
        // Bind parameters with explicit types
        $query->bindParam(":id_term", $id_value, PDO::PARAM_INT);
        $query->bindParam(":string_term", $search_term);
        
        if ($query->execute()) {
            return $query->fetch();
        } else {
            return null;
        }
    }

    // Fetches all customers (e.g., for a customer list view).
    public function viewCustomers($search="", $filter="") {
        // search by both name AND phone_number using the search term
        $sql = "SELECT * FROM " . $this->tableName . " WHERE 
                    name LIKE CONCAT('%', :search, '%') OR 
                    phone_number LIKE CONCAT('%', :search, '%') 
                ORDER BY name ASC";
                
        $query = $this->connect()->prepare($sql);
        $query->bindParam(":search", $search);
        
        if ($query->execute()) {
            return $query->fetchAll(); 
        } else {
            return null;
        }
    }

    public function deleteCustomer($customer_id) {
        $sql = "DELETE FROM " . $this->tableName . " WHERE id = :id";
        
        $query = $this->connect()->prepare($sql);
        $query->bindParam(":id", $customer_id);

        return $query->execute();
    }
}