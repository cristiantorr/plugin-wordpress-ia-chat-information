<?php
class WP_Gemini_Service
{

  private $api_key;
  private $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent";

  public function __construct()
  {
    $this->api_key = defined('GEMINI_API_KEY') ? GEMINI_API_KEY : '';
  }

  public function ask_gemini($context, $question)
  {
    if (empty($this->api_key)) {
      return "Error: No API key configurada para Gemini.";
    }

    $body = [
      "contents" => [[
        "parts" => [[
          "text" => "Contexto: " . $context . "\n\nPregunta: " . $question
        ]]
      ]]
    ];

    $response = wp_remote_post($this->endpoint . "?key=" . $this->api_key, [
      'headers' => [
        'Content-Type' => 'application/json',
      ],
      'body' => json_encode($body),
      'method' => 'POST',
      'data_format' => 'body',
    ]);

    if (is_wp_error($response)) {
      return "Error: " . $response->get_error_message();
    }

    $data = json_decode(wp_remote_retrieve_body($response), true);

    return $data['candidates'][0]['content']['parts'][0]['text'] ?? "No obtuve respuesta de Gemini.";
  }
}
