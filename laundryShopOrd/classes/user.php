<?php

require_once "database.php";

class User extends Database {
    public $username = "";
    public $password = "";
    public $name = "";
    public $role = "staff";

    //Registers a new user with a hashed password.
     
    public function registerUser() {
        $password_hash = password_hash($this->password, PASSWORD_DEFAULT);
        
        $sql = "INSERT INTO user (username, password_hash, name, role) 
                VALUES (:username, :password_hash, :name, :role)";
        
        $query = $this->connect()->prepare($sql);

        $query->bindParam(":username", $this->username);
        $query->bindParam(":password_hash", $password_hash);
        $query->bindParam(":name", $this->name);
        $query->bindParam(":role", $this->role);

        return $query->execute();
    }

    //Authenticates a user for login.
     
    public function loginUser($username, $password) {
        $sql = "SELECT * FROM user WHERE username = :username LIMIT 1";
        $query = $this->connect()->prepare($sql);
        $query->bindParam(":username", $username);

        if ($query->execute()) {
            $user = $query->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password_hash'])) {
                return $user;
            }
        }
        return false;
    }
    
    //Checks if a username already exists.
     
    public function isUserExist($username) {
        $sql = "SELECT COUNT(*) as total FROM user WHERE username = :username";
        $query = $this->connect()->prepare($sql);
        $query->bindParam(":username", $username);

        if ($query->execute()) {
            $record = $query->fetch();
            return $record["total"] > 0;
        }
        return false;
    }
    
    //Counts the total number of users in the system.
    
    public function countAllUsers() {
        $sql = "SELECT COUNT(id) as total FROM user";
        $query = $this->connect()->prepare($sql);

        if ($query->execute()) {
            $record = $query->fetch(PDO::FETCH_ASSOC);
            return (int)$record["total"];
        }
        return 0;
    }

    //Retrieves a single user by ID.
    
    public function fetchUser($uid) {
        $sql = "SELECT id, username, name, role FROM user WHERE id = :id";
        $query = $this->connect()->prepare($sql);
        $query->bindParam(":id", $uid);
        
        if ($query->execute()) {
            return $query->fetch(PDO::FETCH_ASSOC);
        } else {
            return null;
        }
    }
    
    // Retrieves all users for the list view.
     
    public function viewUsers() {
        $sql = "SELECT id, username, name, role, date_created FROM user ORDER BY role ASC, name ASC";
        $query = $this->connect()->prepare($sql);
        
        if ($query->execute()) {
            return $query->fetchAll(PDO::FETCH_ASSOC);
        } else {
            return [];
        }
    }
    
    //Deletes a user by ID.
     
    public function deleteUser($uid) {
        $sql = "DELETE FROM user WHERE id = :id";
        $query = $this->connect()->prepare($sql);
        $query->bindParam(":id", $uid);
        return $query->execute();
    }
}