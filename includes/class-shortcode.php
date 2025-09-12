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
<?php
    return ob_get_clean();
  }
  private function normalize_acf($fields)
  {
    if (empty($fields) || !is_array($fields)) {
      return [];
    }

    $normalized = [];

    foreach ($fields as $key => $value) {
      // Si es un array (ej. repeater o imagen)
      if (is_array($value)) {

        // Caso: imagen de ACF (tiene 'url')
        if (isset($value['url'])) {
          $normalized[$key] = $value['url'];
        }

        // Caso: repeater (array de filas)
        elseif (isset($value[0]) && is_array($value[0])) {
          $normalized[$key] = array_map(function ($row) {
            $rowNormalized = [];
            foreach ($row as $rk => $rv) {
              if (is_array($rv) && isset($rv['url'])) {
                $rowNormalized[$rk] = $rv['url']; // solo URL de la imagen
              } else {
                $rowNormalized[$rk] = $rv;
              }
            }
            return $rowNormalized;
          }, $value);
        }

        // Otro array genérico → lo guardo tal cual
        else {
          $normalized[$key] = $value;
        }
      } else {
        // Valor simple
        $normalized[$key] = $value;
      }
    }

    return $normalized;
  }

  public function handle_ajax()
  {
    $question = sanitize_text_field($_POST['question'] ?? '');

    // --- 1) Traer floorplans con get_posts ---
    $floorplans_query = get_posts([
      'post_type'      => 'floorplans',
      'posts_per_page' => 100,
      'post_status'    => 'publish',
    ]);

    $floorplans = array_map(function ($post) {
      // 1) Obtener ACF
      $acf_fields = function_exists('get_fields') ? $this->normalize_acf(get_fields($post->ID)) : [];

      // 2) Obtener taxonomías dinámicamente
      $taxonomies = get_object_taxonomies('floorplans');
      $terms_by_tax = [];

      foreach ($taxonomies as $tax) {
        $terms = wp_get_post_terms($post->ID, $tax, ['fields' => 'all']);
        if (!is_wp_error($terms) && !empty($terms)) {
          $terms_by_tax[$tax] = array_map(function ($term) {
            return [
              'id'   => $term->term_id,
              'name' => $term->name,
              'slug' => $term->slug,
              'link' => get_term_link($term),
            ];
          }, $terms);
        }
      }

      // 3) Retornar estructura completa
      return [
        'id'                 => $post->ID,
        'title'              => get_the_title($post),
        'slug'               => $post->post_name,
        'excerpt'            => wp_strip_all_tags(get_the_excerpt($post)),
        'content'            => wp_strip_all_tags(wp_trim_words($post->post_content, 50)),
        'link'               => get_permalink($post),
        'featured_image_url' => get_the_post_thumbnail_url($post->ID, 'full'),
        'acf'                => $acf_fields,
        'taxonomies'         => $terms_by_tax, // 👈 Aquí vienen todas las categorías dinámicamente
      ];
    }, $floorplans_query);

    // --- 2) Traer pages con get_posts ---
    $pages_query = get_posts([
      'post_type'      => 'page',
      'posts_per_page' => 100,
      'post_status'    => 'publish',
    ]);

    $pages = array_map(function ($post) {
      return [
        'id'                 => $post->ID,
        'title'              => get_the_title($post),
        'slug'               => $post->post_name,
        'excerpt'            => wp_strip_all_tags(get_the_excerpt($post)),
        'content'            => wp_strip_all_tags(wp_trim_words($post->post_content, 50)),
        'link'               => get_permalink($post),
        'featured_image_url' => get_the_post_thumbnail_url($post->ID, 'full'),
        //'acf'                => function_exists('get_fields') ? get_fields($post->ID) : [],
      ];
    }, $pages_query);

    // --- 3) Crear un resumen breve ---
    $summary = "SITE DATA SUMMARY\n\nPAGES:\n";
    foreach ($pages as $p) {
      $summary .= "- {$p['title']} (slug: {$p['slug']}) | img: " . ($p['featured_image_url'] ?? 'no-img') . "\n";
    }
    $summary .= "\nFLOORPLANS:\n";
    foreach ($floorplans as $f) {
      $summary .= "- {$f['title']} | img: " . ($f['featured_image_url'] ?? 'no-img') . "\n";
    }

    // --- 4) Contexto en JSON ---
    $contextData = [
      'pages'      => $pages,
      'floorplans' => $floorplans,
    ];
    $context_json = wp_json_encode($contextData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    // --- 5) Prompt ---
    $instruction = <<<EOT
Eres un asistente que responde preguntas sobre el sitio web. Tienes dos secciones de datos:
1) PAGES — información de las páginas del sitio (title, slug, excerpt, link, featured_image_url, acf).
2) FLOORPLANS — información de los floorplans (title, link, featured_image_url, acf).

Reglas:
- Si la pregunta se refiere a una página, responde usando SOLO 'PAGES' con sus campos ACF (incluyendo repeater).
- Si la pregunta se refiere a un floorplan, responde usando SOLO 'FLOORPLANS' con sus campos ACF (incluyendo repeater).
- Si el usuario pide imágenes, incluye la URL EXACTA desde 'featured_image_url' en <img src="...">.
- No inventes datos. Si no hay información sobre algunos campos, responde: "Puedes averiguar con un agente al número 3024505859 whatsapp".
- Si la pregunta no está relacionada con temas del sitio web, responde naturalmente.
- Si te demoras en consultar los datos o no hay alguna respuesta del servidor, por favor dar una respuesta clara.

Context summary:
{$summary}

Context JSON (use for exact values):
{$context_json}
EOT;

    // --- 6) Enviar a Gemini ---
    $gemini = new WP_Gemini_Service();
    $answer = $gemini->ask_gemini($instruction, $question);

    // --- 7) Respuesta al frontend ---
    wp_send_json(['answer' => $answer]);
  }
}
