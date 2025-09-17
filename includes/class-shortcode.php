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
    <style>
      .content-form {
        max-width: 800px;
        width: 100%;
        margin: 250px auto;
        padding: 20px;
        border: 1px solid #ccc;
        border-radius: 8px;
        background-color: #f9f9f9;
        position: relative;
      }

      .ask-form {
        display: flex;
        gap: 10px;
      }

      #question {
        flex: 1;
        padding: 10px;
        border: 1px solid #737373;
        border-radius: 4px;
        font-size: 16px;
      }

      button {
        padding: 10px 20px;
        border: none;
        border-radius: 4px;
        background-color: #000;
        color: white;
        font-size: 16px;
        cursor: pointer;
      }

      button:hover {
        background-color: #737373;
      }

      .gemini-qa-response {
        margin-top: 20px;
        padding: 15px;
        border: 1px solid #000;
        border-radius: 4px;
        background-color: #ccc;
        font-size: 16px;
        /*  overflow: auto;
        max-height: 800px; */
      }

      h1 {
        text-align: center;
        margin-bottom: 20px;
        font-size: 18px;
        font-weight: bold;
      }

      .content-main {
        display: flex;
        justify-content: center;
        align-items: center;
        min-height: 300px;
        padding-top: 40px;
        /* height: 100vh; */
      }

      .figure-robot {
        position: absolute;
        top: -130px;
        right: -30px;
        width: 150px;

      }

      .figure-robot img {
        filter: drop-shadow(0px 4px 8px rgba(0, 0, 0, 0.6))
      }
    </style>
    <div class="content-main">

      <div class="content-form">
        <?php the_title('<h1>', '</h1>'); ?>
        <form id="gemini-qa-form" class="ask-form">
          <input type="text" name="question" id="question" placeholder="Ask a question.." required />
          <button type="submit">Question</button>
        </form>
        <div id="gemini-qa-response" class=""></div>
        <figure class="figure-robot"><img src="<?php echo plugin_dir_url(__DIR__); ?>images/robot_bw.png" alt="Robot"></figure>
      </div>
    </div>
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

      if (is_array($value)) {


        if (isset($value['url'])) {
          $normalized[$key] = $value['url'];
        }

        // Caso: repeater (array de filas)
        elseif (isset($value[0]) && is_array($value[0])) {
          $normalized[$key] = array_map(function ($row) {
            $rowNormalized = [];
            foreach ($row as $rk => $rv) {
              if (is_array($rv) && isset($rv['url'])) {
                $rowNormalized[$rk] = $rv['url'];
              } else {
                $rowNormalized[$rk] = $rv;
              }
            }
            return $rowNormalized;
          }, $value);
        } else {
          $normalized[$key] = $value;
        }
      } else {

        $normalized[$key] = $value;
      }
    }

    return $normalized;
  }

  public function handle_ajax()
  {
    $question = sanitize_text_field($_POST['question'] ?? '');


    $floorplans_query = get_posts([
      'post_type'      => 'floorplans',
      'posts_per_page' => 100,
      'post_status'    => 'publish',
    ]);

    $floorplans = array_map(function ($post) {

      $acf_fields = function_exists('get_fields') ? $this->normalize_acf(get_fields($post->ID)) : [];



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


      return [
        'id'                 => $post->ID,
        'title'              => get_the_title($post),
        'slug'               => $post->post_name,
        'excerpt'            => wp_strip_all_tags(get_the_excerpt($post)),
        'content'            => wp_strip_all_tags(wp_trim_words($post->post_content, 50)),
        'link'               => get_permalink($post),
        'featured_image_url' => get_the_post_thumbnail_url($post->ID, 'full'),
        'acf'                => $acf_fields,
        'taxonomies'         => $terms_by_tax,
      ];
    }, $floorplans_query);


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


    $summary = "SITE DATA SUMMARY\n\nPAGES:\n";
    foreach ($pages as $p) {
      $summary .= "- {$p['title']} (slug: {$p['slug']}) | img: " . ($p['featured_image_url'] ?? 'no-img') . "\n";
    }
    $summary .= "\nFLOORPLANS:\n";
    foreach ($floorplans as $f) {
      $summary .= "- {$f['title']} | img: " . ($f['featured_image_url'] ?? 'no-img') . "\n";
      $summary .= "Plano: {$f['title']}\n";

      if (!empty($f['acf']['elevations']) && is_array($f['acf']['elevations'])) {
        foreach ($f['acf']['elevations'] as $index => $elevation) {
          $title = $elevation['elevation_title'] ?? 'Sin título';
          $sf = $elevation['sf'] ?? 'N/D';
          $bedrooms = $elevation['bedrooms'] ?? 'N/D';
          $covered = $elevation['covered'] ?? 'N/D';
          $bathrooms = $elevation['bathrooms'] ?? 'N/D';
          $garage = $elevation['garage'] ?? 'N/D';


          $summary .= "  Elevación " . ($index + 1) . ": {$title} - {$sf}, {$bedrooms}, {$bathrooms}, {$garage}, {$covered}.\n";
        }
      } else {
        $summary .= "  No hay elevaciones disponibles.\n";
      }

      $summary .= "\n";
    }


    $contextData = [
      'pages'      => $pages,
      'floorplans' => $floorplans,
    ];
    $context_json = wp_json_encode($contextData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $whatsapp_url = 'https://wa.me/3024505859';

    $instruction = <<<EOT
You are an assistant that answers questions about the website. You have access to two sections of data:
1) PAGES — information about the site's pages (title, slug, excerpt, link, featured_image_url, acf).
2) FLOORPLANS — information about the floorplans (title, link, featured_image_url, acf).
3) Site name: Canvas Hill.

Rules:
- If the question refers to a page, respond using ONLY the 'PAGES' section with its ACF fields (including repeater fields).
- If the question refers to a floorplan, respond using ONLY the 'FLOORPLANS' section with its ACF fields (including repeater fields).
- If the user asks for images, include the EXACT URL from 'featured_image_url' using <img src="...">.
- Do not invent data. If some fields are missing, respond: "You can check with an agent at <a href="$whatsapp_url" target="_blank">3024505859</a> on WhatsApp."
- Respond in a clear and natural way.
- If there's a delay in retrieving data or the server doesn't respond, provide a clear explanation.
- ALWAYS reply in the same language used by the user (Spanish, English, etc.).


Context summary:
{$summary}

Context JSON (use for exact values):
{$context_json}
EOT;


    $gemini = new WP_Gemini_Service();
    $answer = $gemini->ask_gemini($instruction, $question);

    if (is_wp_error($answer)) {

      $error_message = 'Hubo un problema al procesar tu solicitud. Por favor, intenta nuevamente más tarde.';
      wp_send_json(['error' => $error_message]);
    } else {
      var_dump($answer);
      die();
      $answer_formatted = wpautop($answer); // convierte \n\n en <p>, y \n en <br>
      wp_send_json(['answer' => $answer_formatted]);
    }
  }
}
