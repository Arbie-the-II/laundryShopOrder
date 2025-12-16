<?php
class User {
    private $conn;

    public $id;
    public $username;
    public $password; // Holds the plaintext password temporarily
    public $name;
    public $role;
    public $email;

    public function __construct(PDO $pdo_connection) {
        $this->conn = $pdo_connection;
    }

    // --- 1. FIXED LOGIN METHOD (Uses password_hash column) ---
    public function login($username_or_email, $password) {
        // Fetch the record including the password_hash column
        $sql = "SELECT * FROM user WHERE username = :input OR email = :input LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':input' => $username_or_email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        // Verify using the correct column name: 'password_hash'
        if ($row && password_verify($password, $row['password_hash'])) {
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['role'] = $row['role'];
            $_SESSION['name'] = $row['name'];
            $_SESSION['email'] = $row['email'];
            return true;
        }
        return false;
    }

    // --- 2. FIXED REGISTER METHOD (Inserts into password_hash) ---
    public function registerUser(): bool {
        // Updated column name in INSERT statement
        $sql = "INSERT INTO user (username, password_hash, name, role, email) VALUES (:username, :password, :name, :role, :email)";
        $stmt = $this->conn->prepare($sql);

        $hashed_password = password_hash($this->password, PASSWORD_DEFAULT);

        $stmt->bindParam(':username', $this->username);
        $stmt->bindParam(':password', $hashed_password); // Binds to password_hash column
        $stmt->bindParam(':name', $this->name);
        $stmt->bindParam(':role', $this->role);
        $stmt->bindParam(':email', $this->email);

        return $stmt->execute();
    }

    // --- 3. FIXED PASSWORD UPDATE (Updates password_hash) ---
    public function updatePassword(int $user_id): bool {
        // Updated column name in UPDATE statement
        $sql = "UPDATE user SET password_hash = :password WHERE id = :id";
        $stmt = $this->conn->prepare($sql);

        $hashed_password = password_hash($this->password, PASSWORD_DEFAULT);
        
        $stmt->bindParam(':password', $hashed_password);
        $stmt->bindParam(':id', $user_id);

        return $stmt->execute();
    }

    // --- Standard Methods (No changes needed, but included for completeness) ---

    public function isUsernameExist(string $username, ?int $exclude_user_id = null): bool {
        $sql = "SELECT id FROM user WHERE username = :username";
        if ($exclude_user_id !== null) {
            $sql .= " AND id != :exclude_id";
        }
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':username', $username);
        if ($exclude_user_id !== null) {
            $stmt->bindParam(':exclude_id', $exclude_user_id);
        }
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }

    public function isEmailExist(string $email, ?int $exclude_user_id = null): bool {
        $sql = "SELECT id FROM user WHERE email = :email";
        if ($exclude_user_id !== null) {
            $sql .= " AND id != :exclude_id";
        }
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':email', $email);
        if ($exclude_user_id !== null) {
            $stmt->bindParam(':exclude_id', $exclude_user_id);
        }
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }

    public function fetchUser($user_id) {
        $sql = "SELECT id, username, name, role, email FROM user WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => $user_id]);
        
        
        return $stmt->fetch(PDO::FETCH_ASSOC); 
    }
    
    public function fetchAllUsers(): array {
        $sql = "SELECT id, username, name, role, email, date_created FROM user ORDER BY role, name";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateUser(int $user_id): bool {
        $sql = "UPDATE user SET name = :name, username = :username, email = :email, role = :role WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            ':name' => $this->name,
            ':username' => $this->username,
            ':email' => $this->email,
            ':role' => $this->role,
            ':id' => $user_id
        ]);
    }
    
    public function deleteUser(int $user_id): bool {
        if ($this->isLastAdmin($user_id)) return false;
        $sql = "DELETE FROM user WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([':id' => $user_id]);
    }

    public function isLastAdmin(int $user_id_to_check): bool {
        $sql_count = "SELECT COUNT(id) FROM user WHERE role = 'admin'";
        $stmt_count = $this->conn->prepare($sql_count);
        $stmt_count->execute();
        if ($stmt_count->fetchColumn() > 1) return false;

        $sql_check = "SELECT id FROM user WHERE id = :id AND role = 'admin'";
        $stmt_check = $this->conn->prepare($sql_check);
        $stmt_check->execute([':id' => $user_id_to_check]);
        return $stmt_check->rowCount() == 1;
    }
    
    public function countAllUsers(): int {
        $sql = "SELECT COUNT(id) FROM user";
        $stmt = $this->conn->query($sql);
        return (int)$stmt->fetchColumn();
    }
    
    public function getAdminEmails(): array {
        $sql = "SELECT email FROM user WHERE role = 'admin' AND email IS NOT NULL AND email != ''"; 
        $stmt = $this->conn->query($sql);
        return $stmt->fetchAll(PDO::FETCH_COLUMN); 
    }
}
?>