<?php
class WP_Gemini_QA_Shortcode
{

  public function __construct()
  {
    add_shortcode('gemini_qa', [$this, 'render_shortcode']);
    add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
    add_action('wp_ajax_gemini_qa', [$this, 'handle_ajax']);
    add_action('wp_ajax_nopriv_gemini_qa', [$this, 'handle_ajax']);
  }

  public function enqueue_scripts()
  {
    wp_enqueue_script('gemini-qa', plugin_dir_url(__FILE__) . '../js/gemini-qa.js', ['jquery'], '1.0', true);
    wp_localize_script('gemini-qa', 'geminiQA', [
      'ajax_url' => admin_url('admin-ajax.php'),
    ]);
  }

  public function render_shortcode()
  {
    ob_start(); ?>
    <form id="gemini-qa-form">
      <input type="text" name="question" placeholder="Haz una pregunta..." required />
      <button type="submit">Preguntar</button>
    </form>
    <div id="gemini-qa-response"></div>
<?php return ob_get_clean();
  }

  public function handle_ajax()
  {
    $question = sanitize_text_field($_POST['question'] ?? '');

    // 1. Consultar floorplans desde la REST API
    $response = wp_remote_get(site_url('/wp-json/wp/v2/floorplans?per_page=100'));
    $floorplans = json_decode(wp_remote_retrieve_body($response), true);

    $context = json_encode($floorplans, JSON_PRETTY_PRINT);

    // 2. Pasar a Gemini
    $gemini = new WP_Gemini_Service();
    $answer = $gemini->ask_gemini($context, $question);

    wp_send_json(['answer' => $answer]);
  }
}
