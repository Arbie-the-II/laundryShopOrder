<?php
// Include the database class (assuming it's in the classes folder)
require_once "../classes/database.php"; 

session_start();

// Access Control Check
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

require_once "../classes/pricingmanager.php"; 

// ESTABLISH PDO CONNECTION
$db = new Database();
$pdo_conn = $db->connect();

// Pass the established PDO connection to the PricingManager
$pricingManager = new PricingManager($pdo_conn); 

$message = "";
$error = "";

// 1. Handle Form Submission (POST Request)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate inputs
    $rate_per_lb = filter_input(INPUT_POST, 'rate_per_lb', FILTER_VALIDATE_FLOAT);
    $dry_cleaning = filter_input(INPUT_POST, 'dry_cleaning_surcharge', FILTER_VALIDATE_FLOAT);
    $ironing_only = filter_input(INPUT_POST, 'ironing_only_surcharge', FILTER_VALIDATE_FLOAT);
    $tax_rate_percent = filter_input(INPUT_POST, 'tax_rate_percent', FILTER_VALIDATE_FLOAT); 

    $tax_rate_decimal = $tax_rate_percent / 100;

    if ($rate_per_lb === false || $dry_cleaning === false || $ironing_only === false || $tax_rate_percent === false ||
        $rate_per_lb < 0 || $dry_cleaning < 0 || $ironing_only < 0 || $tax_rate_percent < 0) {
        
        $error = "Error: Please enter valid, non-negative numerical values for all prices and the tax rate.";
    } else {
        
        // --- REAL DB WRITE (using updated PDO method) ---
        if ($pricingManager->updatePricing($rate_per_lb, $dry_cleaning, $ironing_only, $tax_rate_decimal)) {
            $message = "Pricing rates updated successfully! Tax is set to {$tax_rate_percent}%.";
        } else {
            $error = "Failed to update pricing. Database error occurred. Check your server logs.";
        }
    }
} 

// 2. Fetch Current Rates (Always fetch from the database for display)
$rates = $pricingManager->getPricing();

// Prepare tax rate for display in the form 
$display_tax_rate = $rates['tax_rate'] * 100; 

// Helper function for PHP currency formatting (for display only)
function format_php($amount) {
    return '₱' . number_format($amount, 2);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Shop Pricing</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f8f9fa; margin: 0; padding: 20px; }
        .container { max-width: 600px; margin: 30px auto; padding: 25px; background: #fff; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
        h2 { border-bottom: 2px solid #343a40; padding-bottom: 10px; margin-bottom: 25px; color: #343a40; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-weight: bold; margin-bottom: 5px; color: #495057; }
        .form-group input[type="number"] { width: 100%; padding: 10px; border: 1px solid #ced4da; border-radius: 4px; box-sizing: border-box; }
        .form-group small { color: #6c757d; font-size: 0.8em; }
        .alert { padding: 10px; margin-bottom: 15px; border-radius: 4px; }
        .alert-success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-danger { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .btn-submit { background-color: #007bff; color: white; padding: 12px 20px; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; width: 100%; }
        .btn-submit:hover { background-color: #0056b3; }
        .tax-info { color: #495057; font-weight: bold; border: 1px solid #cce5ff; background-color: #e6f2ff; padding: 10px; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="container">
        <a href="../dashboard.php" style="float: right; text-decoration: none; color: #6c757d;">← Back to Dashboard</a>
        <h2>💰 Manage Laundry Shop Pricing (PHP)</h2>
        
        <?php if ($message): ?>
            <div class="alert alert-success">✅ <?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger">❌ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post" action="pricing.php">
            
            <div class="form-group">
                <label for="rate_per_lb">Wash & Fold Price per Pound (PHP)</label>
                <input type="number" id="rate_per_lb" name="rate_per_lb" step="0.01" min="0" 
                       value="<?= htmlspecialchars(number_format($rates['rate_per_lb'], 2, '.', '')) ?>" required>
                <small>This is the base rate charged per pound (0.45 kg) of laundry.</small>
            </div>

            <div class="form-group">
                <label for="dry_cleaning_surcharge">Dry Cleaning Flat Surcharge (PHP)</label>
                <input type="number" id="dry_cleaning_surcharge" name="dry_cleaning_surcharge" step="0.01" min="0"
                       value="<?= htmlspecialchars(number_format($rates['dry_cleaning_surcharge'], 2, '.', '')) ?>" required>
                <small>This flat fee is added to the base weight charge for Dry Cleaning services.</small>
            </div>

            <div class="form-group">
                <label for="ironing_only_surcharge">Ironing Only Flat Surcharge (PHP)</label>
                <input type="number" id="ironing_only_surcharge" name="ironing_only_surcharge" step="0.01" min="0"
                       value="<?= htmlspecialchars(number_format($rates['ironing_only_surcharge'], 2, '.', '')) ?>" required>
                <small>This flat fee is added to the base weight charge for Ironing Only services.</small>
            </div>
            
            <hr style="margin: 20px 0; border-top: 1px dashed #ccc;">
            
            <div class="form-group">
                <label for="tax_rate_percent">Business Tax Rate (%)</label>
                <input type="number" id="tax_rate_percent" name="tax_rate_percent" step="0.01" min="0" max="100"
                       value="<?= htmlspecialchars(number_format($display_tax_rate, 2, '.', '')) ?>" required>
                <small>Enter the tax rate as a percentage (e.g., enter **3** for 3% or **12** for 12%).</small>
            </div>
            <div class="tax-info">
                If your annual sales are PHP 3,000,000 or less, the rate is generally 3% (Percentage Tax). If sales exceed PHP 3,000,000, the rate is typically 12% (VAT).
            </div>

            <button type="submit" class="btn-submit" style="margin-top: 20px;">💾 Update Pricing Rates</button>
        </form>
    </div>
</body>
</html>