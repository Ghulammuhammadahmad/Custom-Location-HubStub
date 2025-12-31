<?php

function clhb_location_cards_shortcode($atts)
{
    // To support ACF fields on the current page
    if (!function_exists('get_field')) {
        return '';
    }

    // Hardcoded JSON - add all locations here, now with "image" key for each location
    $locations_json = '[
        {
            "title": "Comprehensive Spine Center of Dallas – Farmers Branch Clinic",
            "address": "2655 Villa Creek Dr Ste. SW105, Farmers Branch, TX 75234, United States",
            "map_link_label": "[LINK_FARMERS_BRANCH_MAP]",
            "map_link": "https://maps.app.goo.gl/g6RRrMF5WE3RHe3A7",
            "image": "https://dallasspine.com/wp-content/uploads/2025/12/farmerbranch-clinic-loc-photo.webp"
        },
        {
            "title": "Comprehensive Spine Center of Dallas – Fort Worth Clinic",
            "address": "1000 9th Ave suite a, Fort Worth, TX 76104, United States",
            "map_link_label": "[LINK_FORT_WORTH_MAP]",
            "map_link": "https://maps.app.goo.gl/6XHnDGf8fvPZrA6aA",
            "image": "https://dallasspine.com/wp-content/uploads/2025/12/fort-worth-clinic-loc-photo.webp"
        },
        {
            "title": "Comprehensive Spine Center of Dallas – Allen Clinic",
            "address": "1101 Raintree Cir STE 200, Allen, TX 75013, United States",
            "map_link_label": "[LINK_ALLEN_MAP]",
            "map_link": "https://maps.app.goo.gl/rtvNwCaw9cbRipn28",
            "image": "https://dallasspine.com/wp-content/uploads/2025/12/allen-clinic-loc-photo.webp"
        },
        {
            "title": "Comprehensive Spine Center of Dallas – Arlington Clinic",
            "address": "2261 Brookhollow Plaza Dr #111, Arlington, TX 76006, United States",
            "map_link_label": "[LINK_ARLINGTON_MAP]",
            "map_link": "https://maps.app.goo.gl/E1NaPi3rcBftbRSf9",
            "image": "https://dallasspine.com/wp-content/uploads/2025/12/arlington-clinic-loc-photo.webp"
        },
        {
            "title": "Comprehensive Spine Center of Dallas – Frisco Clinic",
            "address": "4577 Ohio Dr suite 140, Frisco, TX 75035, United States",
            "map_link_label": "[LINK_FRISCO_MAP]",
            "map_link": "https://maps.app.goo.gl/eM7NUPQkRYf9KWdF6",
            "image": "https://dallasspine.com/wp-content/uploads/2025/12/frisco-clinic-loc-photo.webp"
        },
        {
            "title": "Comprehensive Spine Center of Dallas – Lancaster Clinic",
            "address": "2700 W Pleasant Run Rd Ste 200, Lancaster, TX 75146, United States",
            "map_link_label": "[LINK_LANCASTER_MAP]",
            "map_link": "https://maps.app.goo.gl/RgQsqfarbu4ZgmUe9",
            "image": "https://dallasspine.com/wp-content/uploads/2025/12/lancaster-clinic-loc-photo.webp"
        },
        {
            "title": "Comprehensive Spine Center of Dallas – Mesquite Clinic",
            "address": "18601 Lyndon B Johnson Fwy #618, Mesquite, TX 75150, United States",
            "map_link_label": "[LINK_MESQUITE_MAP]",
            "map_link": "https://maps.app.goo.gl/MGP6DSPHLRzetUwB7",
            "image": "https://dallasspine.com/wp-content/uploads/2025/12/mesquite-clinic-loc-photo.webp"
        }
    ]';

    $all_locations = json_decode($locations_json, true);

    // Get the stub_closest_clinics ACF repeater field for current post
    global $post;
    if (empty($post))
        return '';

    $repeater = get_field('stub_nearby_clinics', $post->ID);
    if (empty($repeater) || !is_array($repeater)) {
        return '';
    }

    // Build quick map of map_link_label => all_locations entry
    $location_map = [];
    foreach ($all_locations as $loc) {
        if (!empty($loc['map_link_label'])) {
            $location_map[$loc['map_link_label']] = $loc;
        }
    }

    ob_start();
    ?>
    <div class="clhb-location-cards">
        <?php foreach ($repeater as $row):
            // It's possible the sub fields in repeater are called clinicheading, clinicdetails, or location_link_label
            // Try all common variants for the link label
            $label = '';
            // If in JSON no location_link_label but in ACF repeater there is, use that
            if (!empty($row['map_link_label'])) {
                $label = $row['map_link_label'];
            } elseif (!empty($row['clinicheading']) && preg_match('/\[LINK_[A-Z_]+_MAP\]/', $row['clinicheading'], $m)) {
                $label = $m[0];
            } elseif (!empty($row['clinicdetails']) && preg_match('/\[LINK_[A-Z_]+_MAP\]/', $row['clinicdetails'], $m)) {
                $label = $m[0];
            }

            if (empty($label) || empty($location_map[$label])) {
                continue;
            }
            $loc = $location_map[$label];
            ?>
            <div class="clhb-location-card">
                <?php if (!empty($loc['image'])): ?>
                    <div class="clhb-location-card-image">
                        <img src="<?php echo esc_url($loc['image']); ?>" alt="<?php echo esc_attr($loc['title']); ?>"
                            loading="lazy">
                    </div>
                <?php endif; ?>
                <div class="clhb-location-card-title"><strong><?php echo esc_html($loc['title']); ?></strong></div>
                <div class="clhb-location-card-address">
                    <?php echo esc_html($loc['address']); ?><br>
                    Phone: <a href="tel:2144417962">214-441-7962</a>
                    <br>
                    Email: <a href="mailto:scheduling@dallasspine.com">scheduling@dallasspine.com</a>
                </div>
                <div class="clhb-location-card-maplink">
                    <a href="<?php echo esc_url($loc['map_link']); ?>" target="_blank" rel="noopener">
                        View on Map
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <style>
        .clhb-location-cards {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            justify-content: center;
            align-items: stretch;
            margin-bottom: 1em;
        }

        .clhb-location-card {
            border-radius: 12px;
            width: 32%;
            border: 1px solid #074476;
            background: #FFF !important;
            box-shadow: 2px 4px 76px 2px rgba(0, 0, 0, 0.13);
            padding: 2rem;
            display: flex;
            gap: 1.5rem;
            flex-direction: column;
            align-items: flex-start;
        }

        .clhb-location-card-image img {
            width: 100%;
        }

        .clhb-location-card-title {
            font-size: 1.1em;
            text-align: center;
            width: 100%;
        }

        .clhb-location-card-address {
            margin-bottom: 10px;
            color: #333;
            font-size: 1.8rem;
            width: 100%;
            text-align: center;
        }

        .clhb-location-card-maplink {
            border-radius: 8px;
            background: #2EA3F2;
            color: #fff;
            padding: 1.4rem 2.6rem;
            text-decoration: none;
            margin: auto;
        }

        .clhb-location-card-maplink a {
            color: #fff;
        }
    </style>
    <?php

    return ob_get_clean();
}
add_shortcode('clhb_location_cards', 'clhb_location_cards_shortcode');
