<?php
/**
 * Plugin Name: Mi API
 * Description: Un plugin para crear una API personalizada.
 * Version: 1.0
 * Author: Tu Nombre
 */

// Evita el acceso directo al archivo
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


// Registrar las rutas de la API
function mi_api_registrar_rutas() {
    register_rest_route('mi-api/v1', '/posts', array(
        'methods' => 'GET',
        'callback' => 'mi_api_obtener_posts',  // Función de callback que manejará la solicitud
    ));
}

add_action('rest_api_init', 'mi_api_registrar_rutas');

// Función para devolver los posts
function mi_api_obtener_posts(WP_REST_Request $request) {
    // Consultar los últimos posts
    $posts = get_posts(array(
        'numberposts' => 5,  // Número de posts a recuperar
    ));

    if (empty($posts)) {
        return new WP_REST_Response('No posts found', 404);
    }

    // Formatear la respuesta
    $response = array();
    foreach ($posts as $post) {
        $response[] = array(
            'title' => $post->post_title,
            'link' => get_permalink($post),
        );
    }

    return new WP_REST_Response($response, 200);
}
