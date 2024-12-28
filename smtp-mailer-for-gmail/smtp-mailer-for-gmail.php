<?php
/**
 * Plugin Name: SMTP Mailer for Gmail
 * Description: Plugin para enviar correos electrónicos a través de Gmail usando SMTP.
 * Version: 1.0
 * Author: Tu Nombre
 */

// Configuración SMTP para enviar correos con Gmail
define('SMTP_USER', 'mobildev32@gmail.com');  // Tu correo de Gmail
define('SMTP_PASS', 'hfkytuiydqgrbhnw');   // Tu contraseña de Gmail o la contraseña de aplicación si tienes 2FA habilitado
define('SMTP_HOST', 'smtp.gmail.com');        // Servidor SMTP de Gmail
define('SMTP_PORT', '587');                   // Puerto de Gmail (587 para TLS)
define('SMTP_FROM', 'mobildev32@gmail.com');  // Dirección del remitente
define('SMTP_NAME', 'iioDev');             // Nombre del remitente

// Acción para configurar PHPMailer para usar SMTP
add_action('phpmailer_init', 'configure_phpmailer_for_smtp');
function configure_phpmailer_for_smtp($phpmailer) {
    $phpmailer->isSMTP();
    $phpmailer->Host = SMTP_HOST;
    $phpmailer->SMTPAuth = true;
    $phpmailer->Username = SMTP_USER;
    $phpmailer->Password = SMTP_PASS;
    $phpmailer->SMTPSecure = 'tls';
    $phpmailer->Port = SMTP_PORT;
    $phpmailer->setFrom(SMTP_FROM, SMTP_NAME);
}
