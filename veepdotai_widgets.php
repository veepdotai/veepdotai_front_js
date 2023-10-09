<?php
/**
 * @package Test
 * @version 1.0.0
 */
/*
Plugin Name: veepdotai_widgets
Plugin URI: http://wordpress.org/plugins/veepdotai_widgets/
Description: Adds widgets to veepdotai
Author: JC Kermagoret
Version: 0.0.2
Author URI: http://www.veep.ai
*/

require 'vendor/autoload.php';

use ImgFinder\Repository\PexelsRepository;
use ImgFinder\Repository\UnsplashRepository;

if (! defined('WPINC')) {
    die;
}

define('VEEPDOTAI_WIDGETS_VERSION', '1.0.0');
define('VEEPDOTAI_WIDGETS_PLUGIN_VERSION', '1.0.0');

function veepdotai_widgets_enqueue_style() {
    wp_enqueue_style( 'cdn-css-jquery.modal', 'https://cdnjs.cloudflare.com/ajax/libs/jquery-modal/0.9.1/jquery.modal.min.css', false);
    wp_enqueue_style( 'splidejs-css-splide-4.1.4', 'https://cdn.jsdelivr.net/npm/@splidejs/splide@4.1.4/dist/css/splide.min.css', false );
    wp_enqueue_style( 'my-veepdotai-widgets-css-carousel', plugins_url('/veepdotai_widgets/public/assets/carousel/css/carousel.css'), false );
    wp_enqueue_style( 'my-veepdotai-widgets-css-inline-editor' , plugins_url('/veepdotai_widgets/public/assets/inline-editor/css/inline-editor.css'), false);
}                                                                                               

function veepdotai_widgets_enqueue_script() {
    wp_enqueue_script( 'cdn-splidejs-js-splide-4.1.4', 'https://cdn.jsdelivr.net/npm/@splidejs/splide@4.1.4/dist/js/splide.min.js', true );
    wp_enqueue_script( 'my-veepdotai-widgets-js-jquery-3.7.1', plugins_url( '/veepdotai_widgets/public/assets/carousel/js/jquery-3.7.1.js'), true );
    wp_enqueue_script( 'cdn-js-jquery.modal', 'https://cdnjs.cloudflare.com/ajax/libs/jquery-modal/0.9.1/jquery.modal.min.js', true );

    wp_enqueue_script( 'my-veepdotai-widgets-js-loader', plugins_url( '/veepdotai_widgets/public/assets/carousel/js/loader.js' ), true );
    wp_enqueue_script( 'my-veepdotai-widgets-js-carousel', plugins_url( '/veepdotai_widgets/public/assets/carousel/js/carousel.js' ), true );
    wp_enqueue_script( 'my-veepdotai-widgets-js-staticJson', plugins_url( '/veepdotai_widgets/public/assets/carousel/js/staticJson.js' ), true );

    wp_enqueue_script( 'my-veepdotai-widgets-js-inline-editor', plugins_url( '/veepdotai_widgets/public/assets/inline-editor/js/inline-editor.js' ), true );

    wp_localize_script(
        'my-veepdotai-widgets-js-loader',
        'MyAjax',
        array(
            'ajaxurl'  => admin_url( 'admin-ajax.php' ),
            'security' => wp_create_nonce( 'my-special-string' ),
        )
    );
}

add_action( 'wp_enqueue_scripts', 'veepdotai_widgets_enqueue_style' );
add_action( 'wp_enqueue_scripts', 'veepdotai_widgets_enqueue_script' );

add_action( 'wp_ajax_save_featured_image', 'save_featured_image_callback' );
add_action( 'wp_ajax_save_article_inline', 'save_article_inline_callback' );

add_action( 'wp_ajax_get_json_api', 'get_json_api_callback' );

add_action( 'wp_ajax_is_new_image', 'is_new_image_callback' );

function save_featured_image_callback(){
    $url = $_POST['src'];
    $alt = $_POST['alt'];
    $postId = $_POST['postId'];
    $isUnsplash = $_POST['isUnsplash'];
    
    if ($isUnsplash === "true"){
        $url = $url . '&.png';
    }

    $response = media_sideload_image( $url, 0, $url, 'id' );

    if (is_int($response)){
        $imageId = $response;
        update_post_meta($imageId, '_wp_attachment_image_alt', $alt);
        //update_post_meta($imageId, 'url_origin', $url);
        set_post_thumbnail($postId, $imageId);
        $response = get_post_meta($imageId, '_source_url');
    }
    echo json_encode($response);
    die;
}

function save_article_inline_callback(){
    $content = $_POST['content'];
    $postId = $_POST['postId'];

    $postarr = array(
        'ID'            => $postId,
        'post_content'  => $content
    );

    $response = wp_update_post($postarr);

    echo $response;
    die;
}

function get_json_api_callback(){

    $pexelsKey = PEXELS_API_KEY;
    $unsplashKey = UNSPLASH_API_KEY;

    $nbImage = 2;

    $query = $_POST["query"];
    $api = [$_POST["api"]];

    $settings = [
        'img-finder' => [
            'repositories' => [
                PexelsRepository::class => [
                    'params' => [
                        'authorization' => $pexelsKey
                    ]
                ],
                UnsplashRepository::class => [
                    'params' => [
                        'authorization' => $unsplashKey
                    ]
                ]
            ]
        ]
    ];
    
    $config = ImgFinder\Config::fromArray($settings);
    
    $finder = new ImgFinder\ImgFinder($config);
    
    if ($api[0] === "both") {
        $api = ['pexels','unsplash'];
    }else{
        $nbImage *= 2;
    }
    
    $request = ImgFinder\Request::set($query, $api, 1, $nbImage, 'landscape', 1200, 320);
    $response = $finder->search($request);
    
    $imagesUrls = $response->toArray();
    
    echo json_encode($imagesUrls);
    die;
}

function is_new_image_callback(){
    $newUrl = $_POST["url"];
    $postId = $_POST["postId"];

    $args = array(
        'post_type' => 'attachment',
        'post_status' => 'any',  // Spécifie le type de contenu comme "attachment" (pièces jointes)
        'posts_per_page' => -1       // Récupère tous les éléments (images)
    );

    $query = new WP_Query($args);
    
    $is_new = true;
    $newImageId = 0;

    if($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();

            $imageId = get_the_ID();
            
            $urlSource = get_post_meta($imageId, '_source_url')[0];
            $urlSource = str_replace("\\","",$urlSource);

            if ($urlSource === $newUrl){
                $is_new = false;
                $newImageId = $imageId; 
            }
        }
    }

    if (!($is_new)){
        set_post_thumbnail($postId, $newImageId);
    }
    
    echo $is_new;
    die;
}

?>