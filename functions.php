<?php
/**
 * Load cunstom stylesheets
 */
add_action('wp_enqueue_scripts', 'funky_enqueue_styles');
function funky_enqueue_styles() {
    // Parent style (Storefront)
    wp_enqueue_style('storefront-style', get_template_directory_uri() . '/style.css');

    // Child style (depends on parent style)
    wp_enqueue_style('storefront-child-style',
        get_stylesheet_directory_uri() . '/style.css',
        array('storefront-style'),
        wp_get_theme()->get('Version')
    );
}

// Tell WP you support editor styles
add_action( 'after_setup_theme', function() {
  // Registers editor-style.css in your child theme root
  add_theme_support( 'editor-styles' );
  add_editor_style( 'editor-style.css' );
} );


/**
 * Disable the Search Box in the Storefront Theme
 */
add_action( 'init', 'jk_remove_storefront_header_search' );
function jk_remove_storefront_header_search() {
    remove_action( 'storefront_header', 'storefront_product_search', 40 );
}

/**
 * Remove Breadcrumbs
 */
add_action( 'init', 'bbloomer_remove_storefront_breadcrumbs' );
function bbloomer_remove_storefront_breadcrumbs() {
   remove_action( 'storefront_before_content', 'woocommerce_breadcrumb', 10 );
}

/**
 * Remove the Storefront Theme Copyright Link “Built with Storefront”
 */
add_filter( 'storefront_credit_link', '__return_false' );

/*
 *Product Carousel Slider Shortcode by WPCookie
 *[woo-slider card="4" num="10" sale_badge="on" rating="on" description="off" check_stock="on" id="" on_sale="off" cats=""  offset="" type="" ]
 *https://redpishi.com/wordpress-tutorials/product-carousel-slider-shortcode/
 */
function woo_slider_shortcode($atts) {
	if (!function_exists('is_woocommerce')) {
		return;
	}

    // Get Theme colors
    // Accent color (already includes #)
    $accent_color = get_theme_mod( 'storefront_accent_color', '#000000' );
    // Background color (add # prefix)
    $bg_color_value = get_theme_mod( 'background_color', '' );
    $background_color = $bg_color_value ? '#' . $bg_color_value : '#FFFFFF';

    // Parse shortcode attributes
    $atts = shortcode_atts(array(
        'num'         => 10,
        'sale_badge'  => 'on',
        'offset'      => 0,
        'rating'      => 'on',
        'description' => 'off',
        'check_stock' => 'on',
        'on_sale'     => 'off',
        'cats'        => '',
        'tags'        => '',
        'type'        => '',
        'id'          => '',
        'card'        => '4',
        'auto_paly'   => 'off',
        'theme'		  => '1',
        'card-details'=> 'on',

    ), $atts, 'woo-slider');

    $card = $atts["card"];
    $theme = $atts["theme"];
    if ( $atts['auto_paly'] == "on" ) { $auto_paly = true; } else { $auto_paly = "false";}
    static $woo_slider_id = 1;

    // Start building WP_Query arguments
    $args = array(
        'post_type'      => 'product',
        'posts_per_page' => intval($atts['num']),
        'offset'         => intval($atts['offset']),
        'post_status'    => 'publish',
    );

    // Default sorting - newest first
    $args['orderby'] = 'date';
    $args['order'] = 'DESC';

    // Handle specific product IDs
    if (!empty($atts['id']) && $atts['id'] == "6969" ) {
        $product_ids = array_map('intval', explode(',', $atts['id']));
        $args['post__in'] = $product_ids;
        $args['orderby'] = 'post__in'; // Maintain the order of IDs
    } else {
        // Handle stock status
        if ($atts['check_stock'] === 'on') {
            $args['meta_query'][] = array(
                'key'     => '_stock_status',
                'value'   => 'outofstock',
                'compare' => 'NOT IN',
            );
        }

        // Handle sale items - FIX: Use WooCommerce's built-in function instead of custom meta query
        if ($atts['on_sale'] === 'on') {
            $on_sale_ids = wc_get_product_ids_on_sale();
            if (!empty($on_sale_ids)) {
                $args['post__in'] = $on_sale_ids;
            } else {
                // If no products are on sale, return empty results
                $args['post__in'] = array(0);
            }
        }

        // Handle categories and tags sorting
        $tax_based_ordering = false;

        // Handle categories priority sorting
        if (!empty($atts['cats'])) {
            $cat_ids = array_map('intval', explode(',', $atts['cats']));
            $args['tax_query'][] = array(
                'taxonomy' => 'product_cat',
                'field'    => 'term_id',
                'terms'    => $cat_ids,
            );
            $tax_based_ordering = true;
        }

        // Handle tags priority sorting
        if (!empty($atts['tags'])) {
            $tag_ids = array_map('intval', explode(',', $atts['tags']));
            $args['tax_query'][] = array(
                'taxonomy' => 'product_tag',
                'field'    => 'term_id',
                'terms'    => $tag_ids,
            );
            $tax_based_ordering = true;
        }

        // Handle tax_query relation if both categories and tags are specified
        if (!empty($atts['cats']) && !empty($atts['tags'])) {
            $args['tax_query']['relation'] = 'AND';
        }

        // Handle product type (featured or bestselling)
        if ($atts['type'] === 'featured') {
            $args['tax_query'][] = array(
                'taxonomy' => 'product_visibility',
                'field'    => 'name',
                'terms'    => 'featured',
            );
        } elseif ($atts['type'] === 'bestselling') {
            $args['meta_key'] = 'total_sales';
            $args['orderby']  = 'meta_value_num';
            $args['order']    = 'DESC';
        }
    }

    // Run the query
    $products = new WP_Query($args);

    // Start output buffering
    ob_start();

    // Custom sorting for categories or tags priority
    $sorted_posts = array();

    if ((!empty($atts['cats']) || !empty($atts['tags'])) && $products->have_posts()) {
        $wp_query_args = $args;

        // If using categories for priority sorting
        if (!empty($atts['cats'])) {
            $cat_ids = array_map('intval', explode(',', $atts['cats']));

            // Get products for each category separately in the specified order
            foreach ($cat_ids as $cat_id) {
                $cat_query_args = $wp_query_args;

                // Override the tax query for this specific category
                $cat_query_args['tax_query'] = array(
                    array(
                        'taxonomy' => 'product_cat',
                        'field'    => 'term_id',
                        'terms'    => $cat_id,
                    )
                );

                // If we need to maintain stock and sale filters
                if (!empty($wp_query_args['meta_query'])) {
                    $cat_query_args['meta_query'] = $wp_query_args['meta_query'];
                }

                // If we're filtering by on_sale products, maintain that filter
                if (isset($wp_query_args['post__in']) && $atts['on_sale'] === 'on') {
                    $cat_query_args['post__in'] = $wp_query_args['post__in'];
                }

                // Don't limit posts for individual category queries
                $cat_query_args['posts_per_page'] = -1;
                $cat_query_args['fields'] = 'ids'; // Just get IDs to be efficient

                // Get all products from this category
                $cat_products = get_posts($cat_query_args);

                // Add these product IDs to our sorted array
                $sorted_posts = array_merge($sorted_posts, $cat_products);
            }
        }
        // If using tags for priority sorting
        elseif (!empty($atts['tags'])) {
            $tag_ids = array_map('intval', explode(',', $atts['tags']));

            // Get products for each tag separately in the specified order
            foreach ($tag_ids as $tag_id) {
                $tag_query_args = $wp_query_args;

                // Override the tax query for this specific tag
                $tag_query_args['tax_query'] = array(
                    array(
                        'taxonomy' => 'product_tag',
                        'field'    => 'term_id',
                        'terms'    => $tag_id,
                    )
                );

                // If we need to maintain stock and sale filters
                if (!empty($wp_query_args['meta_query'])) {
                    $tag_query_args['meta_query'] = $wp_query_args['meta_query'];
                }

                // If we're filtering by on_sale products, maintain that filter
                if (isset($wp_query_args['post__in']) && $atts['on_sale'] === 'on') {
                    $tag_query_args['post__in'] = $wp_query_args['post__in'];
                }

                // Don't limit posts for individual tag queries
                $tag_query_args['posts_per_page'] = -1;
                $tag_query_args['fields'] = 'ids'; // Just get IDs to be efficient

                // Get all products from this tag
                $tag_products = get_posts($tag_query_args);

                // Add these product IDs to our sorted array
                $sorted_posts = array_merge($sorted_posts, $tag_products);
            }
        }

        // Remove duplicates while preserving order
        $sorted_posts = array_unique($sorted_posts);

        // Limit to the requested number of products
        $sorted_posts = array_slice($sorted_posts, intval($atts['offset']), intval($atts['num']));

        // Now create a new query with these exact product IDs in the correct order
        if (!empty($sorted_posts)) {
            $args = array(
                'post_type'      => 'product',
                'posts_per_page' => -1,
                'post__in'       => $sorted_posts,
                'orderby'        => 'post__in', // Maintain our custom order
            );

            // Replace the original query
            $products = new WP_Query($args);
        }
    }

    if ($products->have_posts()) {
        echo '<div class="blaze-slider" id="woo_slider'.$woo_slider_id.'"><div class="my-structure">
		<span class="blaze-prev" aria-label="Go to previous slide">
		<svg width="40" height="40" version="1.1" viewBox="0 0 10.583 10.583" xmlns="http://www.w3.org/2000/svg">
 <circle cx="5.2964" cy="5.2964" r="4.8304" style="fill:#fff;paint-order:stroke fill markers;stroke-width:.64651"/>
 <path d="m4.7096 3.4245 1.8579 1.8341-1.8579 1.9147" style="fill:none;paint-order:stroke fill markers;stroke-linecap:round;stroke-linejoin:round;stroke-width:.64651;stroke:#1a1a1a"/>
</svg>
</span>
		<span class="blaze-next" aria-label="Go to next slide">
		<svg width="40" height="40" version="1.1" viewBox="0 0 10.583 10.583" xmlns="http://www.w3.org/2000/svg">
 <circle cx="5.2964" cy="5.2964" r="4.8304" style="fill:#fff;paint-order:stroke fill markers;stroke-width:.64651"/>
 <path d="m4.7096 3.4245 1.8579 1.8341-1.8579 1.9147" style="fill:none;paint-order:stroke fill markers;stroke-linecap:round;stroke-linejoin:round;stroke-width:.64651;stroke:#1a1a1a"/>
</svg>
</span>
	  </div><div class="blaze-container"><div class="blaze-track-container"><div class="woo-slider blaze-track">';

        while ($products->have_posts()) {
            $products->the_post();
            global $product;

            if (!$product || !$product->is_visible()) {
                continue;
            }

            // Product card HTML
            ?>
            <div class="post_card">
                <a href="<?php echo esc_url(get_permalink()); ?>" style="position: relative;">
                    <?php
                    // Display product thumbnail
                    echo woocommerce_get_product_thumbnail();

                    // Show sale badge if enabled and product is on sale
                    if ($atts['sale_badge'] === 'on' && $product->is_on_sale()) {
                        echo '<span class="onsale">' . esc_html__('Sale!', 'woocommerce') . '</span>';
                    }
                    ?>
                </a>
            <?php
                if ($atts['card-details'] === 'on') {

                    echo '<span class="wwo_card_details"' . 'style="background-color:' . esc_attr( $accent_color ) . ';">';
                        echo '<a class="p_title" href="' . esc_url(get_permalink()) . '">' . get_the_title() . '</a>';

                        // Rating
                        if ($atts['rating'] === 'on' && $product->get_average_rating() > 0) {
                            echo '<span class="woocommerce">' . wc_get_rating_html($product->get_average_rating()) . '</span>';
                        }

                        // Description
                        if ($atts['description'] === 'on') {
                            echo '<p>' . wp_trim_words(get_the_excerpt(), 15, '...') . '</p>';
                        }

                        // Price
                        echo '<span class="price"><div>' . $product->get_price_html() . '</div></span>';

                        // Add to Cart Button
                        echo sprintf(
                            '<a href="%s" data-quantity="1" class="button add_to_cart_button ajax_add_to_cart" data-product_id="%s" rel="nofollow"'. 'style="background-color:' . esc_attr( $background_color ) . ';">%s</a>',
                            esc_url($product->add_to_cart_url()),
                            esc_attr($product->get_id()),
                            esc_html($product->add_to_cart_text())
                        );
                    echo '</span>';
                }?>
            </div>
            <?php
        }

        echo '</div></div></div><!--<div class="blaze-pagination"></div>--></div>'; // Close .woo-slider

        if ( $woo_slider_id ==1 ) {
			add_action("wp_footer", function(){

				?>

		<script>
		var BlazeSlider=function(){"use strict";const t="start";class e{constructor(t,e){this.config=e,this.totalSlides=t,this.isTransitioning=!1,n(this,t,e)}next(t=1){if(this.isTransitioning||this.isStatic)return;const{stateIndex:e}=this;let n=0,i=e;for(let e=0;e<t;e++){const t=this.states[i];n+=t.next.moveSlides,i=t.next.stateIndex}return i!==e?(this.stateIndex=i,[e,n]):void 0}prev(t=1){if(this.isTransitioning||this.isStatic)return;const{stateIndex:e}=this;let n=0,i=e;for(let e=0;e<t;e++){const t=this.states[i];n+=t.prev.moveSlides,i=t.prev.stateIndex}return i!==e?(this.stateIndex=i,[e,n]):void 0}}function n(t,e,n){t.stateIndex=0,function(t){const{slidesToScroll:e,slidesToShow:n}=t.config,{totalSlides:i,config:s}=t;if(i<n&&(s.slidesToShow=i),!(i<=n)&&(e>n&&(s.slidesToScroll=n),i<e+n)){const t=i-n;s.slidesToScroll=t}}(t),t.isStatic=e<=n.slidesToShow,t.states=function(t){const{totalSlides:e}=t,{loop:n}=t.config,i=function(t){const{slidesToShow:e,slidesToScroll:n,loop:i}=t.config,{isStatic:s,totalSlides:o}=t,r=[],a=o-1;for(let t=0;t<o;t+=n){const n=t+e-1;if(n>a){if(!i){const t=a-e+1,n=r.length-1;(0===r.length||r.length>0&&r[n][0]!==t)&&r.push([t,a]);break}{const e=n-o;r.push([t,e])}}else r.push([t,n]);if(s)break}return r}(t),s=[],o=i.length-1;for(let t=0;t<i.length;t++){let r,a;n?(r=t===o?0:t+1,a=0===t?o:t-1):(r=t===o?o:t+1,a=0===t?0:t-1);const l=i[t][0],c=i[r][0],d=i[a][0];let u=c-l;c<l&&(u+=e);let f=l-d;d>l&&(f+=e),s.push({page:i[t],next:{stateIndex:r,moveSlides:u},prev:{stateIndex:a,moveSlides:f}})}return s}(t)}function i(t){if(t.onSlideCbs){const e=t.states[t.stateIndex],[n,i]=e.page;t.onSlideCbs.forEach((e=>e(t.stateIndex,n,i)))}}function s(t){t.offset=-1*t.states[t.stateIndex].page[0],o(t),i(t)}function o(t){const{track:e,offset:n,dragged:i}=t;e.style.transform=0===n?`translate3d(${i}px,0px,0px)`:`translate3d(  calc( ${i}px + ${n} * (var(--slide-width) + ${t.config.slideGap})),0px,0px)`}function r(t){t.track.style.transitionDuration=`${t.config.transitionDuration}ms`}function a(t){t.track.style.transitionDuration="0ms"}const l=10,c=()=>"ontouchstart"in window;function d(t){const e=this,n=e.slider;if(!n.isTransitioning){if(n.dragged=0,e.isScrolled=!1,e.startMouseClientX="touches"in t?t.touches[0].clientX:t.clientX,!("touches"in t)){(t.target||e).setPointerCapture(t.pointerId)}a(n),p(e,"addEventListener")}}function u(t){const e=this,n="touches"in t?t.touches[0].clientX:t.clientX,i=e.slider.dragged=n-e.startMouseClientX,s=Math.abs(i);s>5&&(e.slider.isDragging=!0),s>15&&t.preventDefault(),e.slider.dragged=i,o(e.slider),!e.isScrolled&&e.slider.config.loop&&i>l&&(e.isScrolled=!0,e.slider.prev())}function f(){const t=this,e=t.slider.dragged;t.slider.isDragging=!1,p(t,"removeEventListener"),t.slider.dragged=0,o(t.slider),r(t.slider),t.isScrolled||(e<-1*l?t.slider.next():e>l&&t.slider.prev())}const h=t=>t.preventDefault();function p(t,e){t[e]("contextmenu",f),c()?(t[e]("touchend",f),t[e]("touchmove",u)):(t[e]("pointerup",f),t[e]("pointermove",u))}const g={slideGap:"20px",slidesToScroll:1,slidesToShow:1,loop:!0,enableAutoplay:!1,stopAutoplayOnInteraction:!0,autoplayInterval:3e3,autoplayDirection:"to left",enablePagination:!0,transitionDuration:300,transitionTimingFunction:"ease",draggable:!0};function v(t){const e={...g};for(const n in t)if(window.matchMedia(n).matches){const i=t[n];for(const t in i)e[t]=i[t]}return e}function S(){const t=this.index,e=this.slider,n=e.stateIndex,i=e.config.loop,s=Math.abs(t-n),o=e.states.length-s,r=s>e.states.length/2&&i;t>n?r?e.prev(o):e.next(s):r?e.next(o):e.prev(s)}function m(t,e=t.config.transitionDuration){t.isTransitioning=!0,setTimeout((()=>{t.isTransitioning=!1}),e)}function x(e,n){const i=e.el.classList,s=e.stateIndex,o=e.paginationButtons;e.config.loop||(0===s?i.add(t):i.remove(t),s===e.states.length-1?i.add("end"):i.remove("end")),o&&e.config.enablePagination&&(o[n].classList.remove("active"),o[s].classList.add("active"))}function y(e,i){const s=i.track;i.slides=s.children,i.offset=0,i.config=e,n(i,i.totalSlides,e),e.loop||i.el.classList.add(t),e.enableAutoplay&&!e.loop&&(e.enableAutoplay=!1),s.style.transitionProperty="transform",s.style.transitionTimingFunction=i.config.transitionTimingFunction,s.style.transitionDuration=`${i.config.transitionDuration}ms`;const{slidesToShow:r,slideGap:a}=i.config;i.el.style.setProperty("--slides-to-show",r+""),i.el.style.setProperty("--slide-gap",a),i.isStatic?i.el.classList.add("static"):e.draggable&&function(t){const e=t.track;e.slider=t;const n=c()?"touchstart":"pointerdown";e.addEventListener(n,d),e.addEventListener("click",(e=>{(t.isTransitioning||t.isDragging)&&(e.preventDefault(),e.stopImmediatePropagation(),e.stopPropagation())}),{capture:!0}),e.addEventListener("dragstart",h)}(i),function(t){if(!t.config.enablePagination||t.isStatic)return;const e=t.el.querySelector(".blaze-pagination");if(!e)return;t.paginationButtons=[];const n=t.states.length;for(let i=0;i<n;i++){const s=document.createElement("button");t.paginationButtons.push(s),s.textContent="",s.ariaLabel=`${i+1} of ${n}`,e.append(s),s.slider=t,s.index=i,s.onclick=S}t.paginationButtons[0].classList.add("active")}(i),function(t){const e=t.config;if(!e.enableAutoplay)return;const n="to left"===e.autoplayDirection?"next":"prev";t.autoplayTimer=setInterval((()=>{t[n]()}),e.autoplayInterval),e.stopAutoplayOnInteraction&&t.el.addEventListener(c()?"touchstart":"mousedown",(()=>{clearInterval(t.autoplayTimer)}),{once:!0})}(i),function(t){const e=t.el.querySelector(".blaze-prev"),n=t.el.querySelector(".blaze-next");e&&(e.onclick=()=>{t.prev()}),n&&(n.onclick=()=>{t.next()})}(i),o(i)}return class extends e{constructor(t,e){const n=t.querySelector(".blaze-track"),i=n.children,s=e?v(e):{...g};super(i.length,s),this.config=s,this.el=t,this.track=n,this.slides=i,this.offset=0,this.dragged=0,this.isDragging=!1,this.el.blazeSlider=this,this.passedConfig=e;const o=this;n.slider=o,y(s,o);let r=!1,a=0;window.addEventListener("resize",(()=>{if(0===a)return void(a=window.innerWidth);const t=window.innerWidth;a!==t&&(a=t,r||(r=!0,setTimeout((()=>{o.refresh(),r=!1}),200)))}))}next(t){if(this.isTransitioning)return;const e=super.next(t);if(!e)return void m(this);const[n,l]=e;x(this,n),m(this),function(t,e){const n=requestAnimationFrame;t.config.loop?(t.offset=-1*e,o(t),setTimeout((()=>{!function(t,e){for(let n=0;n<e;n++)t.track.append(t.slides[0])}(t,e),a(t),t.offset=0,o(t),n((()=>{n((()=>{r(t),i(t)}))}))}),t.config.transitionDuration)):s(t)}(this,l)}prev(t){if(this.isTransitioning)return;const e=super.prev(t);if(!e)return void m(this);const[n,l]=e;x(this,n),m(this),function(t,e){const n=requestAnimationFrame;if(t.config.loop){a(t),t.offset=-1*e,o(t),function(t,e){const n=t.slides.length;for(let i=0;i<e;i++){const e=t.slides[n-1];t.track.prepend(e)}}(t,e);const s=()=>{n((()=>{r(t),n((()=>{t.offset=0,o(t),i(t)}))}))};t.isDragging?c()?t.track.addEventListener("touchend",s,{once:!0}):t.track.addEventListener("pointerup",s,{once:!0}):n(s)}else s(t)}(this,l)}stopAutoplay(){clearInterval(this.autoplayTimer)}destroy(){this.track.removeEventListener(c()?"touchstart":"pointerdown",d),this.stopAutoplay(),this.paginationButtons?.forEach((t=>t.remove())),this.el.classList.remove("static"),this.el.classList.remove(t)}refresh(){const t=this.passedConfig?v(this.passedConfig):{...g};this.destroy(),y(t,this)}onSlide(t){return this.onSlideCbs||(this.onSlideCbs=new Set),this.onSlideCbs.add(t),()=>this.onSlideCbs.delete(t)}}}();

		</script>

			 <?php }  );

        }
    } else {
        echo '<p>' . esc_html__('No products found.', 'woocommerce') . '</p>';
    }
    add_action("wp_footer", function()use ($woo_slider_id, $auto_paly , $card){
    ?>
<script>
    new BlazeSlider(document.querySelector('#woo_slider<?=$woo_slider_id?>'), {
	  all: {
		enableAutoplay: false,
		stopAutoplayOnInteraction: true,
		autoplayInterval: 4000,
		transitionDuration: 300,
		slidesToShow: <?=$card ?>,
		slidesToScroll: 1,
		slideGap: '10px',
		loop: true,
		enablePagination: true,
		transitionDuration: 500,
		transitionTimingFunction: 'ease',
		draggable: true
	  },
	  '(max-width: 900px)': {
		slidesToShow: 2,
	  },
	  '(max-width: 500px)': {
		slidesToShow: 1,
	  },
})


    </script>

    <?php });

    // theme 2 css

    add_action( 'wp_footer', function () use($woo_slider_id, $theme ) { if ( $theme == "2"   ) {  ?>
<style>
div#woo_slider<?=$woo_slider_id?> .slider_card.slide-visible {
    overflow: hidden;
}

div#woo_slider<?=$woo_slider_id?> span.wwo_card_details {
    transition: all 0.3s ease;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
    width: 100%;
    position: absolute;
    inset: 0;
    background-color: #ffffffe6;
    justify-content: center;
    transform: translateY(100%);
}

div#woo_slider<?=$woo_slider_id?>  .post_card:hover span.wwo_card_details {
    transform: translateY(0px);
}
</style>
<?php }
});

    // Reset post data
    wp_reset_postdata();
    $woo_slider_id++;

    // Return the buffered output
    return ob_get_clean();
}

// Register the shortcode
add_shortcode('woo-slider', 'woo_slider_shortcode');
