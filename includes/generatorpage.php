<?php
function clhs_generatorpage_shortcode() {
    if (function_exists('clhs_enqueue_common_assets')) {
        clhs_enqueue_common_assets();
    }
    wp_enqueue_script(
        'clhs-generatepage-js',
        plugin_dir_url(dirname(__FILE__)) . 'assets/js/clhs-generatepage.js',
        array('jquery'),
        file_exists(plugin_dir_path(dirname(__FILE__)) . 'assets/js/clhs-generatepage.js') ? filemtime(plugin_dir_path(dirname(__FILE__)) . 'assets/js/clhs-generatepage.js') : '1.0.0',
        true
    );
    wp_localize_script(
        'clhs-generatepage-js',
        'clhsGeneratorAjax',
        array(
            'ajax_url' => admin_url('admin-ajax.php')
        )
    );
    wp_enqueue_style(
        'clhs-generatorstyle-css',
        plugin_dir_url(dirname(__FILE__)) . 'assets/css/clhs-generatorstyle.css',
        array(),
        file_exists(plugin_dir_path(dirname(__FILE__)) . 'assets/css/clhs-generatorstyle.css') ? filemtime(plugin_dir_path(dirname(__FILE__)) . 'assets/css/clhs-generatorstyle.css') : '1.0.0'
    );

    ob_start();
?>
<div class="generatorpage">
    <form method="post" action="options.php">
        <?php
        settings_fields('clhs_settings_group');
        do_settings_sections('clhs-main-menu');
        submit_button();
        ?>
    </form>
    <H3>Generate Pages</H3>
    <p>Generate pages using the AI generator. This will create a new page in the WordPress database.</p>
    <button class="button button-primary" id="clhs-generate-pages">Generate Pages</button>
    <div id="clhs-generate-pages-result"></div>
</div>
<?php
    return ob_get_clean();
}
add_shortcode('clhs-generatorpage', 'clhs_generatorpage_shortcode');


// AJAX handler for generating pages
function clhs_acf_schema_from_group($field_group_key) {
    $acf_schema = [
        "type" => "object",
        "properties" => [],
        "required" => []
    ];

    // Get all fields in this group
    $fields = acf_get_fields($field_group_key);

    if ($fields) {
        foreach ($fields as $field) {

            $field_slug = $field['name'];
            $field_label = $field['label'];
            $field_type = $field['type'];      // ACF field type
            $field_required = !empty($field['required']); 

            // Convert ACF type to JSON Schema type
            switch ($field_type) {
                case 'text':
                case 'textarea':
                case 'wysiwyg':
                case 'email':
                case 'url':
                    $json_type = "string";
                    break;

                case 'number':
                    $json_type = "number";
                    break;

                case 'true_false':
                    $json_type = "boolean";
                    break;

                case 'select':
                case 'checkbox':
                case 'radio':
                    // for multi-select use array => "array" + items
                    if ($field_type == 'checkbox') {
                        $json_type = "array";
                    } else {
                        $json_type = "string";
                    }
                    break;

                case 'repeater':
                    $json_type = "array";
                    break;

                default:
                    $json_type = "string"; 
            }

            // build property JSON schema
            $schema_item = [
                "type" => $json_type,
                "description" => $field_label
            ];

            // If it's a checkbox, define items as string array
            if ($field_type === 'checkbox') {
                $schema_item["items"] = [
                    "type" => "string"
                ];
            }

            // ----------- PATCH FOR OPENAI: Ensure required/include in arr.item object -----------
            // If it's an array field, it MUST have items and any object "items" must have "required" and "additionalProperties": false
            if ($json_type === 'array' && empty($schema_item['items'])) {

                // If it's a repeater and has sub_fields, describe items as an object
                if ($field_type === 'repeater' && !empty($field['sub_fields']) && is_array($field['sub_fields'])) {

                    $item_props = [];
                    foreach ($field['sub_fields'] as $sub) {
                        $sub_type = $sub['type'];
                        $sub_json_type = "string";

                        switch ($sub_type) {
                            case 'number':     $sub_json_type = "number"; break;
                            case 'true_false': $sub_json_type = "boolean"; break;
                            case 'checkbox':   $sub_json_type = "array"; break;
                            default:           $sub_json_type = "string"; break;
                        }

                        $sub_schema = [
                            "type" => $sub_json_type,
                            "description" => $sub['label'] ?? $sub['name']
                        ];

                        if ($sub_type === 'checkbox') {
                            $sub_schema["items"] = ["type" => "string"];
                        }
                        if ($sub_json_type === 'array' && empty($sub_schema["items"])) {
                            $sub_schema["items"] = ["type" => "string"];
                        }

                        $item_props[$sub['name']] = $sub_schema;
                    }

                    // NEW: required - mark all properties as required
                    $required_keys = array_keys($item_props);

                    $schema_item["items"] = [
                        "type" => "object",
                        "properties" => $item_props,
                        "required" => $required_keys,
                        "additionalProperties" => false,
                    ];

                } else {
                    // Non-repeater arrays (or no sub_fields): default to array of strings
                    // But if you ever know that items are objects (custom), you can instead build accordingly
                    $schema_item["items"] = ["type" => "string"];
                }

            }
            // --------- END PATCH ---------

            $acf_schema['properties'][$field_slug] = $schema_item;

            if ($field_required) {
                $acf_schema['required'][] = $field_slug;
            }
        }
    }

    return $acf_schema;
}

function get_json_from_openai($acf_schema, $user_input = null, $extra_instructions = '') {
    $api_key    = get_option('clhs_openai_api_key', '');
    $model_name = get_option('clhs_openai_model', '');
    $prompt     = get_option('clhs_page_name', '');

    if (empty($api_key)) {
        return new WP_Error('openai_missing_key', 'OpenAI API key is missing.');
    }
    if (empty($model_name)) {
        // You can set a default if you want
        $model_name = 'gpt-5.2';
    }
    if (!is_array($acf_schema) || empty($acf_schema)) {
        return new WP_Error('openai_bad_schema', 'Schema must be a non-empty PHP array.');
    }

    // --- Make the schema "strict": require all properties and disallow extra keys.
    $schema = $acf_schema;

    // Add meta_title, meta_description, and slug at the end of the schema
    $schema['properties']['meta_title'] = [
        "type" => "string",
        "description" => "Meta Title for SEO"
    ];
    $schema['properties']['meta_description'] = [
        "type" => "string",
        "description" => "Meta Description for SEO"
    ];
    $schema['properties']['slug'] = [
        "type" => "string",
        "description" => "Page slug (URL-friendly name without parent use only alphabets and - only)"
    ];

    if (isset($schema['properties']) && is_array($schema['properties'])) {
        $all_keys = array_keys($schema['properties']);
        $schema['required'] = $all_keys; // require all, including meta_title/meta_description
    } else {
        return new WP_Error('openai_bad_schema', 'Schema must contain a "properties" object.');
    }

    $schema['additionalProperties'] = false;

    // --- Build input messages (Responses API supports role-based input arrays)
    $messages = [];

    // Use "instructions" for system/developer guidance (recommended for Responses API)
    $instructions = trim($prompt);

    // Add extra_instructions if provided
    if (!empty($extra_instructions)) {
        $instructions = ($instructions ? $instructions . " " : "") . $extra_instructions;
    }

    if ($instructions === '') {
        $instructions = 'Generate a JSON object that matches the provided schema.';
    }

    $user_text = $user_input ? (string) $user_input : 'Generate content for these fields. Use the files attached in a tools for instructions.';
    $messages[] = [
        "role" => "user",
        "content" => [
            ["type" => "input_text", "text" => $user_text]
        ]
    ];

    $payload = [
        "model" => $model_name,
        "instructions" => $instructions,
        "input" => $messages,
        "tools" => [
            [
                "type" => "file_search",
                "vector_store_ids" => [
                    "vs_695395d17a4c81918dc134bbda289d70"
                ]
            ],
            [
                "type" => "web_search"
            ]
        ],
        "text" => [
            "format" => [
                "type"   => "json_schema",
                "name"   => "stub",
                "strict" => true,
                "schema" => $schema,
            ],
            "verbosity" => "medium",
        ],
        "reasoning" => [
            "effort"  => "medium",
            "summary" => "auto",
        ],
        "store" => true,
        "include" => [
            "reasoning.encrypted_content",
            "web_search_call.action.sources"
        ]
    ];
// print_r($payload);
echo "<br>Generating Content......";
// flush();
    $ch = curl_init('https://api.openai.com/v1/responses');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $api_key,
        ],
        CURLOPT_POSTFIELDS     => wp_json_encode($payload),
        CURLOPT_CONNECTTIMEOUT => 15,   // time to establish TCP/TLS
        CURLOPT_TIMEOUT        => 250,  // total request time
    ]);

    $raw  = curl_exec($ch);
    $errno = curl_errno($ch);
    $err  = curl_error($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errno) {
        return new WP_Error('openai_curl_error', 'cURL error: ' . $err);
    }
    if ($raw === false || $raw === '') {
        return new WP_Error('openai_empty_response', 'Empty response from OpenAI.');
    }

    $resp = json_decode($raw, true);
    if (!is_array($resp)) {
        return new WP_Error('openai_bad_json', 'OpenAI response was not valid JSON.', [
            'http_code' => $code,
            'raw' => $raw,
        ]);
    }

    if ($code < 200 || $code >= 300) {
        $msg = $resp['error']['message'] ?? 'OpenAI API error.';
        return new WP_Error('openai_api_error', $msg, [
            'http_code' => $code,
            'response' => $resp,
        ]);
    }

    // --- Extract the assistant text (which should be JSON due to json_schema format)
    $text_out = null;

    // Common pattern: output[] contains message items with content[] parts.
    if (!empty($resp['output']) && is_array($resp['output'])) {
        foreach ($resp['output'] as $item) {
            if (($item['type'] ?? '') === 'message' && ($item['role'] ?? '') === 'assistant') {
                $content = $item['content'] ?? [];
                if (is_array($content)) {
                    foreach ($content as $part) {
                        if (($part['type'] ?? '') === 'output_text' && isset($part['text'])) {
                            $text_out = $part['text'];
                            break 2;
                        }
                    }
                }
            }
        }
    }

    // Fallback: some SDKs expose output_text; keep this just in case.
    if ($text_out === null && isset($resp['output_text'])) {
        $text_out = $resp['output_text'];
    }

    if ($text_out === null) {
        return new WP_Error('openai_no_text', 'Could not find assistant text in OpenAI response.', [
            'response' => $resp,
        ]);
    }

    $json_obj = json_decode($text_out, true);
    if (!is_array($json_obj)) {
        return new WP_Error('openai_model_not_json', 'Model output was not valid JSON.', [
            'model_text' => $text_out,
            'response' => $resp,
        ]);
    }

    return $json_obj;
}

function generate_pagefrom_openairesponse($aijsonresult, $pagename) {
    $parentpage  = (int) get_option('clhs_parent_page_id', 0);
    $template_id = (int) get_option('clhs_elementor_template_id', 0);

    if (empty($pagename)) {
        return new WP_Error('clhs_missing_pagename', 'Page name is required.');
    }
    if (!is_array($aijsonresult) || empty($aijsonresult)) {
        return new WP_Error('clhs_missing_ai', 'AI response must be a non-empty array.');
    }

    // 1) Create or update the page
    $existing = get_page_by_title($pagename, OBJECT, 'page');

    $postarr = [
        'post_title'   => $pagename,
        'post_type'    => 'page',
        'post_status'  => 'publish',
        'post_parent'  => $parentpage > 0 ? $parentpage : 0,
    ];

    // Use slug from AI response if provided
    if (!empty($aijsonresult['slug'])) {
        $postarr['post_name'] = sanitize_title($aijsonresult['slug']);
    }

    if ($existing && !is_wp_error($existing)) {
        $postarr['ID'] = $existing->ID;
        $page_id = wp_update_post($postarr, true);
    } else {
        $page_id = wp_insert_post($postarr, true);
    }

    if (is_wp_error($page_id)) {
        return $page_id;
    }

    // 2) Copy Elementor template content into this page
    if ($template_id > 0 && did_action('elementor/loaded')) {
        try {
            $template_doc = \Elementor\Plugin::$instance->documents->get($template_id);

            if ($template_doc) {
                $elements_data = $template_doc->get_elements_data();

                // Required meta to mark as Elementor-built
                update_post_meta($page_id, '_elementor_edit_mode', 'builder');
                update_post_meta($page_id, '_elementor_data', wp_slash(wp_json_encode($elements_data)));

                // Optional but often helpful
                if (method_exists(\Elementor\Plugin::$instance, 'get_version')) {
                    update_post_meta($page_id, '_elementor_version', \Elementor\Plugin::$instance->get_version());
                }
            } else {
                // Template not found / not accessible
                return new WP_Error('clhs_template_missing', 'Elementor template not found for ID: ' . $template_id);
            }
        } catch (\Throwable $e) {
            return new WP_Error('clhs_elementor_copy_failed', 'Failed to copy Elementor template: ' . $e->getMessage());
        }
    } elseif ($template_id > 0) {
        // Elementor not loaded but template was requested
        return new WP_Error('clhs_elementor_not_loaded', 'Elementor is not loaded; cannot copy template.');
    }

    // 3) Fill ACF fields (keys in $aijsonresult MUST match ACF field "name")
    // Exclude special fields: meta_title, meta_description, slug
    $special_fields = array('meta_title', 'meta_description', 'slug');
    if (function_exists('update_field')) {
        foreach ($aijsonresult as $field_name => $value) {
            // Skip special fields that are handled separately
            if (in_array($field_name, $special_fields, true)) {
                continue;
            }
            // This works if $field_name is the ACF field "name" (slug)
            update_field($field_name, $value, $page_id);
        }
        
        // Set stub_title to the page name
        update_field('stub_title', $pagename, $page_id);
    } else {
        return new WP_Error('clhs_acf_missing', 'ACF update_field() not available.');
    }

    // 4) Add meta_title and meta_description to Rank Math SEO meta if present in AI JSON result
    if (!empty($aijsonresult['meta_title'])) {
        update_post_meta($page_id, 'rank_math_title', $aijsonresult['meta_title']);
    }
    if (!empty($aijsonresult['meta_description'])) {
        update_post_meta($page_id, 'rank_math_description', $aijsonresult['meta_description']);
    }

    // Return the permalink (URL) of the generated or updated page
    $page_url = get_permalink($page_id);
    if (!$page_url) {
        return new WP_Error('clhs_get_permalink_failed', 'Failed to get page URL.', [
            'page_id' => $page_id,
        ]);
    }

    return $page_url;
}


function clhs_handle_generate_pages() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Unauthorized'), 403);
    }

    @ignore_user_abort(true);
    @set_time_limit(0);

    header('Content-Type: text/plain; charset=utf-8');
    header('X-Accel-Buffering: no');
    while (ob_get_level()) { ob_end_flush(); }
    ob_implicit_flush(true);

    $page_names = get_option('clhs_page_name', '');
    $page_options = 'stub'; // Always use stub

    $group_title = 'Stub Custom Fields';
    $field_groups = acf_get_field_groups();
    $field_group_key = '';
    foreach ($field_groups as $group) {
        if ($group['title'] === $group_title) {
            $field_group_key = $group['key'];
            break;
        }
    }
    if ($field_group_key) {
        $acf_schema = clhs_acf_schema_from_group($field_group_key);
    } else {
        return new WP_Error('clhs_acf_field_not_found', 'ACF Group not found: ');
    }

    // Output JSON Schema for debugging
    echo "ACF Schema:<br>";
    echo json_encode($acf_schema, JSON_PRETTY_PRINT);
    echo "<br>---<br>";
    flush();

    // Prepare page names - split if comma separated.
    $page_names_arr = array_filter(array_map('trim', explode(',', $page_names)));
    if (empty($page_names_arr)) {
        echo "No page names provided.<br>";
        flush();
    } else {
        foreach ($page_names_arr as $page_name) {
            // Before generating, check if the page is already generated by exact title
            $existing_page = get_page_by_title($page_name, OBJECT, 'page');
            if ($existing_page && !is_wp_error($existing_page)) {
                // Page exists, skip it
                echo "Skipped: Page '{$page_name}' already exists (ID: {$existing_page->ID}).<br>";
                flush();
                continue;
            }

            // ----- Customization: for stub, append parent page info to instructions -----
            $extra_instructions = '';

            if ($page_options === 'stub') {
                $parent_page_id = (int) get_option('clhs_parent_page_id', 0);
                $parent_page_title = '';
                if ($parent_page_id > 0) {
                    $parent_page = get_post($parent_page_id);
                    if ($parent_page && !is_wp_error($parent_page)) {
                        $parent_page_title = $parent_page->post_title;
                    }
                }
                if (!empty($parent_page_title)) {
                    $extra_instructions = 'After producing the Mode D Page, output a JSON export that matches the field names and repeater structure in the provided schema. Use identical content, just transformed into JSON.';
                }
            }
            // --------------------------------------------------------------------------

            // Add user prompt/context with the current page name
            $user_input = 'Generate content for these fields. use br tag for line break but when required. use html list when required if nested list item not exist. instruction: "' . $page_name . '"';
            echo "Generating for instruction: {$page_name}.......<br>";
            flush();
            
            // Use the instruction (which contains the page names) as the main prompt
            // The instruction field value is already being used in get_json_from_openai via get_option('clhs_page_name')
            $aijsonresult = get_json_from_openai($acf_schema, $user_input, $extra_instructions);
            echo "AI Response: ".json_encode($aijsonresult, JSON_PRETTY_PRINT);
            echo "<br>---<br>";
            flush();

            $pagegeneratedurl = generate_pagefrom_openairesponse($aijsonresult, $page_name);

            echo "<a href='".esc_url($pagegeneratedurl)."'>".esc_html($pagegeneratedurl)."</a>";
            flush();
        }
    }
    
    echo "\n✅ Page generation completed successfully.<br>";
    flush();
    wp_die(); 
}
add_action('wp_ajax_clhs_generate_pages', 'clhs_handle_generate_pages');
add_action('wp_ajax_nopriv_clhs_generate_pages', 'clhs_handle_generate_pages');