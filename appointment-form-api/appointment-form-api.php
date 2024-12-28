<?php
/**
 * Plugin Name: Appointment Form API
 * Description: Plugin para manejar el envío de citas.
 * Version: 1.0
 * Author: Rene Rauda
 */

// Verificar permisos de usuario
function check_appointment_permissions() {
    return true; // Permitir acceso a todos (puedes ajustar esta lógica según tus necesidades)
}

// Crear la tabla de citas cuando se activa el plugin
function create_appointment_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'appointments'; 
    $charset_collate = $wpdb->get_charset_collate();

    // Verificar si la tabla ya existe
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
        // La tabla ya existe, realizar las comprobaciones y actualizaciones necesarias
        
        // Verificar las columnas actuales de la tabla
        $columns = $wpdb->get_results("SHOW COLUMNS FROM $table_name");

        // Array de columnas esperadas
        $expected_columns = [
            'first_name',
            'last_name',
            'dob',
            'phone_number',
            'email',
            'contact_preference',
            'doctor_note',
            'appointment_day_and_time',
            'time_since_last_visit',  // Columna nueva que queremos verificar
            'created_at'
        ];

        // Iterar sobre las columnas esperadas
        foreach ($expected_columns as $column) {
            $column_exists = false;
            
            // Verificar si la columna ya existe en la tabla
            foreach ($columns as $col) {
                if ($col->Field === $column) {
                    $column_exists = true;
                    break;
                }
            }

            // Si la columna no existe, agregarla
            if (!$column_exists) {
                if ($column === 'time_since_last_visit') {
                    $wpdb->query("ALTER TABLE $table_name ADD COLUMN $column varchar(255) DEFAULT ''");
                }
                // Puedes agregar más columnas aquí si es necesario
            }
        }
    } else {
        // Si la tabla no existe, crearla desde cero
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            first_name varchar(255) NOT NULL,
            last_name varchar(255) NOT NULL,
            dob date NOT NULL,
            phone_number varchar(20) NOT NULL,
            email varchar(255) NOT NULL,
            contact_preference varchar(50) NOT NULL,
            doctor_note text NOT NULL,
            appointment_day_and_time varchar(255) NOT NULL,  -- Nueva columna para el día y turno
            time_since_last_visit varchar(255) DEFAULT '',  -- Cambiado a VARCHAR para almacenar un texto
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY email (email)  -- Asegura que el email sea único en la tabla
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}

register_activation_hook(__FILE__, 'create_appointment_table');

// Función para guardar los detalles de la cita
// Función para guardar los detalles de la cita y enviar correo
function save_appointment_details(WP_REST_Request $request) {
    $data = $request->get_json_params();

    // Validar campos requeridos
    if (empty($data['firstName']) || empty($data['lastName']) || empty($data['dob']) || empty($data['email'])) {
        return new WP_REST_Response('Missing required fields', 400);
    }

    // Validar formato de la fecha (Y-m-d)
    $dob = sanitize_text_field($data['dob']);
    if (!DateTime::createFromFormat('Y-m-d', $dob)) {
        return new WP_REST_Response('Invalid date format, expected Y-m-d', 400);
    }

    // Obtener el tiempo desde la última visita (en formato de texto)
    $time_since_last_visit = isset($data['timeSinceLastVisit']) ? sanitize_text_field($data['timeSinceLastVisit']) : '';

    global $wpdb;
    $table_name = $wpdb->prefix . 'appointments';

    // Verificar si el correo electrónico ya está registrado
    $existing_appointment = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $table_name WHERE email = %s",
        sanitize_email($data['email'])
    ));

    if ($existing_appointment) {
        return new WP_REST_Response('This email address is already registered for an appointment', 400);
    }

    // Insertar la cita en la base de datos
    $inserted = $wpdb->insert(
        $table_name,
        array(
            'first_name'        => sanitize_text_field($data['firstName']),
            'last_name'         => sanitize_text_field($data['lastName']),
            'dob'               => $dob,
            'phone_number'      => sanitize_text_field($data['phoneNumber'] ?? ''),
            'email'             => sanitize_email($data['email']),
            'contact_preference'=> sanitize_text_field($data['contactPreference'] ?? ''),
            'doctor_note'       => sanitize_textarea_field($data['doctorNote'] ?? ''),
            'appointment_day_and_time' => sanitize_text_field($data['appointmentDateTime']),
            'time_since_last_visit' => $time_since_last_visit,  // Almacenar como VARCHAR
        )
    );

    // Verificar si la inserción fue exitosa
    if ($inserted === false) {
        return new WP_REST_Response('Error saving appointment', 500);
    }

    // Enviar correo electrónico
    $email_sent = msf_send_email_handler($data); // Llamada a la función que envia el correo

    if ($email_sent) {
        return new WP_REST_Response('Appointment saved and email sent successfully', 200);
    } else {
        return new WP_REST_Response('Appointment saved, but email sending failed', 500);
    }
}

// Función para enviar el correo de la cita
function msf_send_email_handler($data) {
    $to = 'mobildev32@gmail.com';  // Correo de destino
    $subject = "New Appointment Submission from " . $data['firstName'] . " " . $data['lastName'];
    $message = "
        <h2>Appointment Details</h2>
        <p><strong>First Name:</strong> {$data['firstName']}</p>
        <p><strong>Last Name:</strong> {$data['lastName']}</p>
        <p><strong>Date of Birth:</strong> {$data['dob']}</p>
        <p><strong>Phone Number:</strong> {$data['phoneNumber']}</p>
        <p><strong>Email:</strong> {$data['email']}</p>
        <p><strong>Contact Preference:</strong> {$data['contactPreference']}</p>
        <p><strong>Doctor's Note:</strong> {$data['doctorNote']}</p>
        <p><strong>Appointment Date and Time:</strong> {$data['appointmentDateTime']}</p>
        <p><strong>Time Since Last Visit:</strong> {$data['timeSinceLastVisit']}</p>
    ";

    $headers = [
        'Content-Type: text/html; charset=UTF-8',
        'From: Appointment System <mobildev32@gmail.com>',
    ];

    return wp_mail($to, $subject, $message, $headers);
}


// Registrar la ruta de la API REST
function register_appointment_endpoint() {
    register_rest_route('appointment/v1', '/save', array(
        'methods' => 'POST',
        'callback' => 'save_appointment_details',
        'permission_callback' => 'check_appointment_permissions',
    ));
}
add_action('rest_api_init', 'register_appointment_endpoint');
