<?php
require_once "../classes/database.php"; 
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: ../login.php"); exit; }
require_once "../classes/pricingmanager.php"; 

$db = new Database();
$pdo_conn = $db->connect();
$pricingManager = new PricingManager($pdo_conn); 
$message = ""; $error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $rate_per_lb = filter_input(INPUT_POST, 'rate_per_lb', FILTER_VALIDATE_FLOAT);
    $dry_cleaning = filter_input(INPUT_POST, 'dry_cleaning_surcharge', FILTER_VALIDATE_FLOAT);
    $ironing_only = filter_input(INPUT_POST, 'ironing_only_surcharge', FILTER_VALIDATE_FLOAT);
    $tax_rate_percent = filter_input(INPUT_POST, 'tax_rate_percent', FILTER_VALIDATE_FLOAT); 

    if ($rate_per_lb === false || $rate_per_lb < 0) { $error = "Invalid input."; } 
    else {
        if ($pricingManager->updatePricing($rate_per_lb, $dry_cleaning, $ironing_only, $tax_rate_percent / 100)) {
            $message = "Pricing updated.";
        } else { $error = "Update failed."; }
    }
} 
$rates = $pricingManager->getPricing();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Pricing</title>
    <style>
        /* --- LAYOUT STYLES --- */
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; background-color: #f8f9fa; }
        .wrapper { display: flex; min-height: 100vh; }
        .sidebar { width: 250px; background-color: #343a40; color: #fff; padding-top: 20px; flex-shrink: 0; }
        .content { flex-grow: 1; padding: 20px; display: flex; justify-content: center; }
        /* --------------------- */
        .container { max-width: 600px; width: 100%; padding: 25px; background: #fff; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); height: fit-content; }
        h2 { border-bottom: 2px solid #343a40; padding-bottom: 10px; margin-bottom: 25px; color: #343a40; }
        .form-group { margin-bottom: 15px; }
        label { display: block; font-weight: bold; margin-bottom: 5px; color: #495057; }
        input[type="number"] { width: 100%; padding: 10px; border: 1px solid #ced4da; border-radius: 4px; box-sizing: border-box; }
        .btn-submit { background-color: #007bff; color: white; padding: 12px; border: none; border-radius: 4px; cursor: pointer; width: 100%; font-weight: bold; }
        .alert { padding: 10px; margin-bottom: 15px; border-radius: 4px; }
        .alert-success { background: #d4edda; color: #155724; }
    </style>
</head>
<body>
    <div class="wrapper">
        <?php include "../includes/sidebar.php"; ?>
        <div class="content">
            <div class="container">
                <h2>💰 Manage Pricing</h2>
                <?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
                <?php if ($error): ?><div class="alert alert-danger" style="background:#f8d7da; color:#721c24;"><?= htmlspecialchars($error) ?></div><?php endif; ?>

                <form method="post">
                    <div class="form-group">
                        <label>Price per Pound (PHP)</label>
                        <input type="number" name="rate_per_lb" step="0.01" value="<?= $rates['rate_per_lb'] ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Dry Cleaning Surcharge</label>
                        <input type="number" name="dry_cleaning_surcharge" step="0.01" value="<?= $rates['dry_cleaning_surcharge'] ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Ironing Only Surcharge</label>
                        <input type="number" name="ironing_only_surcharge" step="0.01" value="<?= $rates['ironing_only_surcharge'] ?>" required>
                    </div>
                    <hr>
                    <div class="form-group">
                        <label>Tax Rate (%)</label>
                        <input type="number" name="tax_rate_percent" step="0.01" value="<?= $rates['tax_rate'] * 100 ?>" required>
                    </div>
                    <button type="submit" class="btn-submit">💾 Update Rates</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>