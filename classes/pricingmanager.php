<?php

class PricingManager {
    // Fallback constants used ONLY if DB query fails or returns no row
    const DEFAULT_RATE_PER_LB = 82.50; 
    const DEFAULT_DRY_CLEANING_SURCHARGE = 275.00;
    const DEFAULT_IRONING_ONLY_SURCHARGE = 165.00;
    const DEFAULT_TAX_RATE = 0.03; 

    private $conn; 

    /*Accepts the PDO connection object upon instantiation.*/
    public function __construct($pdo_connection) {
        $this->conn = $pdo_connection;
    }

    /*Fetches the current pricing settings from the database using PDO.*/
    private function fetchSettingsFromDB() {
        $sql = "SELECT rate_per_lb, dry_cleaning_surcharge, ironing_only_surcharge, tax_rate FROM pricing_settings LIMIT 1";
        
        try {
            // Prepare and execute the statement
            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                // Return values as floating-point numbers
                return [
                    'rate_per_lb' => (float)$row['rate_per_lb'],
                    'dry_cleaning_surcharge' => (float)$row['dry_cleaning_surcharge'],
                    'ironing_only_surcharge' => (float)$row['ironing_only_surcharge'],
                    'tax_rate' => (float)$row['tax_rate']
                ];
            }
        } catch (PDOException $e) {
            // Log or handle the error, then fall through to return defaults
        }

        // Return hardcoded defaults if DB query fails or table is empty
        return [
            'rate_per_lb' => self::DEFAULT_RATE_PER_LB,
            'dry_cleaning_surcharge' => self::DEFAULT_DRY_CLEANING_SURCHARGE,
            'ironing_only_surcharge' => self::DEFAULT_IRONING_ONLY_SURCHARGE,
            'tax_rate' => self::DEFAULT_TAX_RATE
        ];
    }

    /**
     * Retrieves all current pricing and tax settings.
     * @return array
     */
    public function getPricing() {
        return $this->fetchSettingsFromDB(); 
    }

    /*Updates the pricing settings in the database using PDO prepared statements.*/
    public function updatePricing(float $rate_per_lb, float $dry_cleaning, float $ironing, float $tax_rate_decimal) {
        // Use named placeholders for security and clarity
        $sql = "UPDATE pricing_settings SET 
                rate_per_lb = :rate_per_lb, 
                dry_cleaning_surcharge = :dry_cleaning, 
                ironing_only_surcharge = :ironing, 
                tax_rate = :tax_rate 
                WHERE id = 1"; 

        $stmt = $this->conn->prepare($sql);
        
        $result = $stmt->execute([
            ':rate_per_lb' => $rate_per_lb,
            ':dry_cleaning' => $dry_cleaning,
            ':ironing' => $ironing,
            ':tax_rate' => $tax_rate_decimal
        ]);

        return $result;
    }
}