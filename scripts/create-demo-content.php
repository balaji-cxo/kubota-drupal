<?php

/**
 * Adds screenshot-inspired demo content for Dealer, Product and Landing Page.
 *
 * Run once with: ddev drush php:script scripts/create-demo-content.php
 * Requires the three content structures and files in demo-assets/.
 */

use Drupal\block_content\Entity\BlockContent;
use Drupal\file\Entity\File;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;

foreach (['dealer', 'product', 'landing_page'] as $bundle) {
  $count = \Drupal::entityQuery('node')
    ->accessCheck(FALSE)
    ->condition('type', $bundle)
    ->count()
    ->execute();
  if ($count) {
    throw new RuntimeException("$bundle already contains entries. No demo content was added.");
  }
}
foreach (['banner', 'benefits_strip', 'testimonial'] as $bundle) {
  $count = \Drupal::entityQuery('block_content')
    ->accessCheck(FALSE)
    ->condition('type', $bundle)
    ->count()
    ->execute();
  if ($count) {
    throw new RuntimeException("$bundle blocks already exist. No demo content was added.");
  }
}
foreach (['product_category', 'brand', 'machine_make', 'machine_type', 'machine_model'] as $vocabulary) {
  $count = \Drupal::entityQuery('taxonomy_term')
    ->accessCheck(FALSE)
    ->condition('vid', $vocabulary)
    ->count()
    ->execute();
  if ($count) {
    throw new RuntimeException("$vocabulary terms already exist. No demo content was added.");
  }
}

function demo_image(string $filename): int {
  $uri = 'public://demo-assets/' . $filename;
  if (!file_exists($uri) || filesize($uri) === 0) {
    throw new RuntimeException("Copy a nonempty demo image to $uri before running this script.");
  }
  $file = File::create([
    'uri' => $uri,
    'filename' => $filename,
    'filemime' => 'image/png',
    'filesize' => filesize($uri),
    'status' => 1,
    'uid' => 1,
  ]);
  $file->save();
  return (int) $file->id();
}

$track_image = demo_image('rubber-track.png');
$hero_image = demo_image('home-hero.png');
$promo_image = demo_image('promo-loader.png');
$dealer_image = demo_image('dealer-storefront.png');

$category = Term::create([
  'vid' => 'product_category',
  'name' => 'Rubber Tracks',
  'description' => ['value' => 'Engineered tracks for compact and mid-size equipment.', 'format' => 'basic_html'],
  'field_category_image' => ['target_id' => $track_image, 'alt' => 'Black rubber track'],
]);
$category->save();

$brand = Term::create(['vid' => 'brand', 'name' => 'TerraGear']);
$brand->save();
$make = Term::create(['vid' => 'machine_make', 'name' => 'Caterpillar']);
$make->save();
$machine_type = Term::create(['vid' => 'machine_type', 'name' => 'Compact Track Loader']);
$machine_type->save();
$model = Term::create([
  'vid' => 'machine_model',
  'name' => 'Caterpillar 259D',
  'field_machine_model_code' => '259D',
  'field_machine_make' => ['target_id' => $make->id()],
  'field_machine_type' => ['target_id' => $machine_type->id()],
]);
$model->save();

$hero_benefits = BlockContent::create([
  'type' => 'benefits_strip',
  'info' => 'Home hero trust points (Demo)',
  'status' => 1,
  'uid' => 1,
  'field_benefit_1_heading' => 'OEM Quality',
  'field_benefit_2_heading' => 'Field Tested',
  'field_benefit_3_heading' => 'Global Support',
  'field_benefit_4_heading' => 'Built to Last',
]);
$hero_benefits->save();

$service_benefits = BlockContent::create([
  'type' => 'benefits_strip',
  'info' => 'Service benefits (Demo)',
  'status' => 1,
  'uid' => 1,
  'field_benefit_1_heading' => 'Fast Shipping',
  'field_benefit_1_text' => 'Across North America',
  'field_benefit_2_heading' => 'Guaranteed Fitment',
  'field_benefit_2_text' => 'Or Your Money Back',
  'field_benefit_3_heading' => 'Industry-Leading Warranty',
  'field_benefit_3_text' => '12 Months / 1,500 Hours',
  'field_benefit_4_heading' => 'Expert Support',
  'field_benefit_4_text' => 'Real People. Real Help.',
]);
$service_benefits->save();

$hero = BlockContent::create([
  'type' => 'banner',
  'info' => 'Home hero (Demo)',
  'status' => 1,
  'uid' => 1,
  'field_banner_eyebrow' => 'PRECISION PARTS. PROVEN DURABILITY.',
  'field_banner_heading' => 'Precision parts. Guaranteed fitment.',
  'field_banner_text' => ['value' => 'Engineered for maximum uptime in agriculture and construction.', 'format' => 'basic_html'],
  'field_banner_image' => ['target_id' => $hero_image, 'alt' => 'Yellow excavator at a mountain worksite'],
  'field_banner_benefits' => ['target_id' => $hero_benefits->id()],
]);
$hero->save();

$testimonial = BlockContent::create([
  'type' => 'testimonial',
  'info' => 'Fleet manager quote (Demo)',
  'status' => 1,
  'uid' => 1,
  'field_testimonial_quote' => ['value' => 'TerraGear tracks have improved our machine uptime and reduced replacement costs. Excellent quality and support.', 'format' => 'basic_html'],
  'field_testimonial_person' => 'Demo Customer',
  'field_testimonial_role' => 'Fleet Manager',
  'field_testimonial_company' => 'Construction Company',
]);
$testimonial->save();

$product = Node::create([
  'type' => 'product',
  'title' => 'TG450x86x55',
  'status' => 1,
  'uid' => 1,
  'field_part_number' => 'TG450x86x55',
  'field_product_category' => ['target_id' => $category->id()],
  'field_product_brand' => ['target_id' => $brand->id()],
  'field_product_image' => ['target_id' => $track_image, 'alt' => 'TG450x86x55 black rubber track'],
  'field_product_short_description' => 'Engineered for maximum durability and performance in compact and mid-size equipment.',
  'field_product_badge' => 'best_seller',
  'field_product_gallery' => [['target_id' => $track_image, 'alt' => 'TG450x86x55 tread and inner guides']],
  'field_product_description' => ['value' => 'A reinforced rubber track designed for dependable traction and long service life on compact track loaders.', 'format' => 'basic_html'],
  'field_product_key_features' => [
    ['value' => 'Heavy duty tread design'],
    ['value' => 'Excellent traction and stability'],
    ['value' => 'Cut and wear resistant'],
    ['value' => 'Optimized for various terrains'],
    ['value' => 'Long service life'],
  ],
  'field_width_mm' => '450',
  'field_pitch_mm' => '86',
  'field_links' => 55,
  'field_weight_kg' => '78',
  'field_tread_pattern' => 'C-Lug',
  'field_material' => 'Reinforced Rubber',
  'field_operating_temp_min_c' => '-30',
  'field_operating_temp_max_c' => '80',
  'field_primary_application' => ['target_id' => $machine_type->id()],
  'field_compatible_machines' => [['target_id' => $model->id()]],
]);
$product->save();

$related_product = Node::create([
  'type' => 'product',
  'title' => 'TG400x72x52',
  'status' => 1,
  'uid' => 1,
  'field_part_number' => 'TG400x72x52',
  'field_product_category' => ['target_id' => $category->id()],
  'field_product_brand' => ['target_id' => $brand->id()],
  'field_product_image' => ['target_id' => $track_image, 'alt' => 'TG400x72x52 black rubber track'],
  'field_product_short_description' => 'Durable rubber track for compact track loader applications.',
  'field_width_mm' => '400',
  'field_pitch_mm' => '72',
  'field_links' => 52,
  'field_primary_application' => ['target_id' => $machine_type->id()],
  'field_compatible_machines' => [['target_id' => $model->id()]],
]);
$related_product->save();

$promo = BlockContent::create([
  'type' => 'banner',
  'info' => 'Partners in Your Uptime (Demo)',
  'status' => 1,
  'uid' => 1,
  'field_banner_heading' => 'Partners in Your Uptime',
  'field_banner_text' => ['value' => 'From fields to job sites, TerraGear delivers high-performance tracks, tires and undercarriage parts you can trust.', 'format' => 'basic_html'],
  'field_banner_image' => ['target_id' => $promo_image, 'alt' => 'Yellow wheel loader at a construction site'],
  'field_banner_cta' => ['uri' => 'internal:/node/' . $product->id(), 'title' => 'Learn More'],
  'field_banner_benefits' => ['target_id' => $service_benefits->id()],
]);
$promo->save();
$product->set('field_product_promo_banner', ['target_id' => $promo->id()]);
$product->set('field_related_products', [['target_id' => $related_product->id()]]);
$product->save();

$dealer = Node::create([
  'type' => 'dealer',
  'title' => 'Midwest Equipment Co.',
  'status' => 1,
  'uid' => 1,
  'field_dealer_photo' => ['target_id' => $dealer_image, 'alt' => 'Construction equipment dealership storefront'],
  'field_dealer_street_address' => '1234 Industrial Way',
  'field_dealer_city' => 'Cincinnati',
  'field_dealer_state' => 'OH',
  'field_dealer_zip_code' => '45242',
  'field_dealer_phone' => '+1 513 555 0100',
  'field_dealer_email' => 'midwest@example.com',
  'field_dealer_latitude' => '39.2281000',
  'field_dealer_longitude' => '-84.3625000',
]);
$dealer->save();

$home = Node::create([
  'type' => 'landing_page',
  'title' => 'Home (Demo)',
  'status' => 1,
  'uid' => 1,
  'field_hero_banner' => ['target_id' => $hero->id()],
  'field_popular_categories' => [['target_id' => $category->id()]],
  'field_featured_products' => [
    ['target_id' => $product->id()],
    ['target_id' => $related_product->id()],
  ],
  'field_promotional_banner' => ['target_id' => $promo->id()],
  'field_benefits_strip' => ['target_id' => $service_benefits->id()],
  'field_testimonials' => [['target_id' => $testimonial->id()]],
]);
$home->save();

echo "Created demo Landing Page node {$home->id()}, Product nodes {$product->id()} and {$related_product->id()}, and Dealer node {$dealer->id()}.\n";
