<?php
// Adjust this path if your vendor folder is somewhere else
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class Mailer {
    
    // --- ⚙️ SMTP CONFIGURATION ---
     private $host       = 'smtp.gmail.com';
    private $username   = 'orderlaundryshop@gmail.com'; // CHANGE THIS
    private $password   = 'ejgk eude emcf hdyz';         // CHANGE THIS (App Password)
    private $port       = 587;
    private $encryption = PHPMailer::ENCRYPTION_STARTTLS;
    
    // 3. CONFIGURE SHOP DETAILS
    private $from_email = "no-reply@laundryshop.com"; // Does not need to be real
    private $from_name  = "Laundry Shop System";
    private $base_url   = "http://localhost/laundryShopOrd"; // Ensure this matches your URL

    /**
     * Internal helper to send email
     */
    private function send($to_email, $subject, $body_html) {
        $mail = new PHPMailer(true);

        try {
            // Server settings
            $mail->isSMTP();                                            
            $mail->Host       = $this->host;                     
            $mail->SMTPAuth   = true;                                   
            $mail->Username   = $this->username;                     
            $mail->Password   = $this->password;                               
            $mail->SMTPSecure = $this->encryption;            
            $mail->Port       = $this->port;                                    

            // Recipients
            $mail->setFrom($this->username, $this->from_name); 
            $mail->addAddress($to_email);     

            // Content
            $mail->isHTML(true);                                  
            $mail->Subject = $subject;
            $mail->Body    = $body_html;
            $mail->AltBody = strip_tags($body_html);

            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Mailer Error: {$mail->ErrorInfo}");
            return false;
        }
    }

    // --- PUBLIC METHODS ---

    public function sendVerificationEmail($to_email, $customer_name, $token) {
        $subject = "Verify Your Email - " . $this->from_name;
        $verification_link = $this->base_url . "/order/verify_customer.php?token=" . $token;

        $message = "
        <div style='font-family: Arial, sans-serif; padding: 20px; border: 1px solid #ddd; border-radius: 10px;'>
            <h2 style='color: #007bff;'>Welcome, $customer_name!</h2>
            <p>Please click the button below to verify your email address:</p>
            <p style='text-align: center;'>
                <a href='$verification_link' style='background-color: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Verify Email Now</a>
            </p>
            <p><small>Or copy this link: $verification_link</small></p>
        </div>";

        return $this->send($to_email, $subject, $message);
    }

    public function sendOrderStatusUpdate($to_email, $customer_name, $order_id, $new_status) {
        $subject = "Order Update #$order_id - " . $this->from_name;

        // --- CUSTOM MESSAGES BASED ON STATUS ---
        $status_message_extra = "";
        $status_color = "#6c757d"; // Default Grey

        if ($new_status == 'Ready for Pickup') {
            $status_color = "#28a745"; // Green
            $status_message_extra = "
            <div style='margin-top: 20px; padding: 15px; background-color: #e8f5e9; border-left: 5px solid #28a745; color: #155724;'>
                <strong>🎉 Good news!</strong><br>
                Your laundry is ready. Please visit our shop to pick up your items.
            </div>";
        } elseif ($new_status == 'Processing') {
            $status_color = "#17a2b8"; // Blue
            $status_message_extra = "<p>We have started washing/drying your clothes.</p>";
        }

        $message = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: auto; padding: 20px; border: 1px solid #ddd; border-radius: 10px;'>
            <h2 style='color: #007bff;'>Order Status Update</h2>
            <p>Hi <strong>$customer_name</strong>,</p>
            <p>The status of your laundry order <strong>#$order_id</strong> has been updated.</p>
            
            <div style='background-color: #f8f9fa; padding: 15px; text-align: center; margin: 20px 0; border-radius: 5px;'>
                <span style='font-size: 1.2em;'>Current Status:</span><br>
                <strong style='font-size: 1.6em; color: $status_color;'>$new_status</strong>
            </div>

            $status_message_extra

            <p style='margin-top: 30px;'>Thank you for choosing us!</p>
            <p style='color: #888; font-size: 0.9em;'>Regards,<br>" . $this->from_name . "</p>
        </div>";

        return $this->send($to_email, $subject, $message);
    }
    
    public function sendAdminAlert($admin_emails, $subject, $message_body) {
        if (empty($admin_emails)) return false;
        $subject = "[Admin Alert] " . $subject;
        
        $message = "
        <div style='font-family: Arial, sans-serif; padding: 20px; border: 1px solid #ddd; border-left: 5px solid #dc3545;'>
            <h2 style='color: #dc3545; margin-top: 0;'>Admin Notification</h2>
            <p>$message_body</p>
            <hr>
            <p><small>Automated System Message</small></p>
        </div>";

        foreach ($admin_emails as $email) {
            $this->send($email, $subject, $message);
        }
        return true;
    }
}
?>
