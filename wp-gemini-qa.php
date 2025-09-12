<?php

/**
 * Plugin Name: WP Gemini QA
 * Description: Hazle preguntas a tu WordPress (ej: floorplans) usando Gemini AI.
 * Version: 1.0.0
 * Author: Cristian Torres
 */

if (! defined('ABSPATH')) exit;

require_once plugin_dir_path(__FILE__) . 'includes/class-gemini-service.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-shortcode.php';

// Inicializar shortcode
add_action('init', function () {
  new WP_Gemini_QA_Shortcode();
});
