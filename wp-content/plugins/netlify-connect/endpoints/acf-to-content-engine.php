<?php
if (! defined('ABSPATH')) exit; // Exit if accessed directly

function netlify_endpoints_permission_callback() {
    return current_user_can('manage_options') || current_user_can('edit_posts');
}

add_action('rest_api_init', function () {
    register_rest_route('acf-to-content-engine/v1', '/(?P<postType>[a-zA-Z0-9_-]+)/fields', array(
        'methods' => 'GET',
        'callback' => 'get_acf_fields_content_engine_schema',
        'permission_callback' => "netlify_endpoints_permission_callback",
        'args' => array(
            'postType' => array(
                'validate_callback' => function ($param) {
                    return post_type_exists($param);
                }
            ),
        ),
    ));
});

function get_acf_fields_content_engine_schema($request) {
    if (!function_exists('acf_get_field_groups')) {
        return new WP_Error('500', 'ACF is not installed', array('status' => 500));
    }
    $post_type = $request['postType'];

    $field_groups = acf_get_field_groups(array('post_type' => $post_type));
    $fields_data = array();

    foreach ($field_groups as $group) {
        if (isset($group['show_in_rest']) && $group['show_in_rest']) {
            $fields = acf_get_fields($group['key']);
            foreach ($fields as $field) {
                $processed_field = process_acf_field($field);
                if ($processed_field !== null) {
                    $fields_data[] = $processed_field;
                }
            }
        }
    }

    $data = [
        'type' => 'object',
        'name' => 'acf',
        'label' => 'Fields',
        'fields' => $fields_data,
    ];

    return new WP_REST_Response($data, 200);
}

function process_acf_field($field) {
    // Skip 'message' and 'accordion' fields as these are purely presentational in the ACF UI
    if (in_array($field["type"], ["message", "accordion"])) {
        return null;
    }
    $type_mapping = [
        'post_object' => 'contentNode',
        'page_link' => 'contentNode',
        'relationship' => 'contentNode',
        'taxonomy' => 'taxonomyNode',

        'text' => 'String',
        'textarea' => 'String',
        'email' => 'String',
        'url' => 'String',
        'password' => 'String',
        'wysiwyg' => 'String',
        'oembed' => 'String',
        'checkbox' => 'String',
        'button_group' => 'String',
        'google_map' => 'String',
        'time_picker' => 'String',
        'color_picker' => 'String',
        'tab' => 'String',

        'number' => 'Float',
        'range' => 'Float',

        'true_false' => 'Boolean',

        'image' => 'media',
        'file' => 'media',
        'gallery' => 'media',

        'link' => 'link',
        'user' => 'user',

        'date_picker' => 'Date',
        'date_time_picker' => 'Date',

        'select' => 'enum',
        'radio' => 'enum',

        'group' => 'group',
        'repeater' => 'group',
        'flexible_content' => 'flexibleContent',
        'clone' => 'group',
    ];

    $mapped_type = $type_mapping[$field["type"]] ?? 'String';

    $list = !empty($field['multiple']) ||
        in_array(
            $field["type"],
            [
                "checkbox",
                "gallery",
                "taxonomy",
                "relationship",
                "flexible_content",
                "repeater"
            ]
        );

    $field_data = [
        'name' => $field['name'],
        'label' => $field['label'],
        'type' => $mapped_type,
        'required' => !empty($field['required']),
        'list' => $list,
    ];

    // Handle options for select and radio fields
    if (in_array($field["type"], ["select", "radio"])) {
        $options = [];
        foreach ($field["choices"] as $key => $value) {
            $options[] = [
                'label' => $value,
                'value' => $key,
            ];
        }
        $field_data['options'] = $options;
    }

    if ($field["type"] === "flexible_content") {
        $layouts_data = [];
        foreach ($field["layouts"] as $layout) {
            $sub_fields_data = [];
            if (!isset($layout["sub_fields"])) {
                return null;
            }
            foreach ($layout["sub_fields"] as $sub_field) {
                $sub_fields_data[] = process_acf_field($sub_field);
            }
            if (empty($sub_fields_data)) {
                continue;
            }
            $layouts_data[] = [
                'name' => $layout['name'],
                'label' => $layout['label'],
                'type' => 'object',
                'fields' => $sub_fields_data,
            ];
        }
        if (empty($layouts_data)) {
            return null;
        }
        $field_data['items'] = [
            'type' => 'object',
            'fields' => $layouts_data,
        ];
    } elseif (in_array($field["type"], ["group", "repeater", "clone"])) {
        $sub_fields_data = [];
        if (!isset($field["sub_fields"])) {
            return null;
        }
        foreach ($field["sub_fields"] as $sub_field) {
            $sub_fields_data[] = process_acf_field($sub_field);
        }
        $field_data['sub_fields'] = $sub_fields_data;
    } elseif ($field["type"] === "link") {
        $field_data['fields'] = [
            [
                'name' => 'url',
                'label' => 'URL',
                'type' => 'String'
            ],
            [
                'name' => 'title',
                'label' => 'Title',
                'type' => 'String'
            ],
            [
                'name' => 'target',
                'label' => 'Target',
                'type' => 'String'
            ],
        ];
    }

    return $field_data;
}
