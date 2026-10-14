<?php

/**
 * @file
 * Idempotent site configuration and sample data for the TerraGear POC.
 *
 * Run with: ddev drush php:script scripts/configure-terragear-poc.php
 */

use Drupal\block\Entity\Block;
use Drupal\block_content\Entity\BlockContent;
use Drupal\comment\Entity\Comment;
use Drupal\comment\Entity\CommentType;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\file\FileInterface;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy_menu\Entity\TaxonomyMenu;
use Drupal\user\Entity\Role;

$manager = \Drupal::entityTypeManager();

function poc_find(string $entity_type, array $conditions) {
  $matches = \Drupal::entityTypeManager()->getStorage($entity_type)->loadByProperties($conditions);
  return reset($matches) ?: NULL;
}

function poc_field(string $entity_type, string $bundle, string $name, string $type, string $label, array $storage_settings = [], array $settings = [], int $cardinality = 1): void {
  if (!FieldStorageConfig::loadByName($entity_type, $name)) {
    FieldStorageConfig::create([
      'entity_type' => $entity_type,
      'field_name' => $name,
      'type' => $type,
      'cardinality' => $cardinality,
      'settings' => $storage_settings,
    ])->save();
  }
  if (!FieldConfig::loadByName($entity_type, $bundle, $name)) {
    FieldConfig::create([
      'entity_type' => $entity_type,
      'bundle' => $bundle,
      'field_name' => $name,
      'label' => $label,
      'settings' => $settings,
    ])->save();
  }
}

// Product Reviews are comments on a Product, not taxonomy or reusable blocks.
if (!CommentType::load('product_review')) {
  CommentType::create([
    'id' => 'product_review',
    'label' => 'Product Review',
    'target_entity_type_id' => 'node',
    'description' => 'Editor-managed rating and review of a product.',
  ])->save();
}
poc_field('node', 'product', 'field_product_reviews', 'comment', 'Product Reviews',
  ['comment_type' => 'product_review'],
  ['default_mode' => 1, 'per_page' => 20, 'anonymous' => 0, 'form_location' => FALSE, 'preview' => 0]);
$review_field = FieldConfig::loadByName('node', 'product', 'field_product_reviews');
$review_field->setDefaultValue([['status' => 2]])->save();
poc_field('comment', 'product_review', 'comment_body', 'text_long', 'Review');
poc_field('comment', 'product_review', 'field_rating', 'integer', 'Rating', [], ['min' => 1, 'max' => 5]);
$review_form = \Drupal::service('entity_display.repository')->getFormDisplay('comment', 'product_review');
$review_form->setComponent('comment_body', ['type' => 'text_textarea', 'weight' => 0]);
$review_form->setComponent('field_rating', ['type' => 'number', 'weight' => 1]);
$review_form->save();

// A bounded drag-and-drop section field on each editor-owned content bundle.
foreach ([
  'editorial_text' => 'Editorial Text',
  'editorial_image' => 'Editorial Image',
  'editorial_products' => 'Curated Products',
  'editorial_layout' => 'Layout Section',
  'category_grid' => 'Category Grid',
  'product_grid' => 'Featured Product Grid',
  'promo_banner' => 'Promotional Banner',
  'testimonial_slider' => 'Testimonial Slider',
  'benefits_strip' => 'Benefits Strip',
] as $id => $label) {
  if (!ParagraphsType::load($id)) {
    ParagraphsType::create(['id' => $id, 'label' => $label])->save();
  }
}
poc_field('paragraph', 'editorial_text', 'field_heading', 'string', 'Heading');
poc_field('paragraph', 'editorial_text', 'field_text', 'text_long', 'Text');
poc_field('paragraph', 'editorial_image', 'field_heading', 'string', 'Heading');
poc_field('paragraph', 'editorial_image', 'field_image', 'image', 'Image', [], ['file_directory' => 'editorial']);
poc_field('paragraph', 'editorial_products', 'field_heading', 'string', 'Heading');
poc_field('paragraph', 'editorial_products', 'field_products', 'entity_reference', 'Products',
  ['target_type' => 'node'], ['handler' => 'default:node', 'handler_settings' => ['target_bundles' => ['product' => 'product']]], -1);
poc_field('paragraph', 'category_grid', 'field_heading', 'string', 'Heading');
poc_field('paragraph', 'category_grid', 'field_categories', 'entity_reference', 'Categories',
  ['target_type' => 'taxonomy_term'], ['handler' => 'default:taxonomy_term', 'handler_settings' => ['target_bundles' => ['product_category' => 'product_category']]], -1);
poc_field('paragraph', 'product_grid', 'field_heading', 'string', 'Heading');
poc_field('paragraph', 'product_grid', 'field_products', 'entity_reference', 'Products',
  ['target_type' => 'node'], ['handler' => 'default:node', 'handler_settings' => ['target_bundles' => ['product' => 'product']]], -1);
poc_field('paragraph', 'promo_banner', 'field_banner', 'entity_reference', 'Banner', ['target_type' => 'block_content']);
poc_field('paragraph', 'testimonial_slider', 'field_heading', 'string', 'Heading');
poc_field('paragraph', 'testimonial_slider', 'field_testimonials', 'entity_reference', 'Testimonials', ['target_type' => 'block_content'], [], -1);
poc_field('node', 'landing_page', 'field_page_hero_heading', 'string', 'Page Hero Heading');
poc_field('node', 'landing_page', 'field_page_hero_text', 'text_long', 'Page Hero Text');
if (!FieldStorageConfig::loadByName('node', 'field_editorial_sections')) {
  FieldStorageConfig::create([
    'entity_type' => 'node',
    'field_name' => 'field_editorial_sections',
    'type' => 'entity_reference_revisions',
    'cardinality' => -1,
    'settings' => ['target_type' => 'paragraph'],
  ])->save();
}
foreach (['landing_page', 'product', 'dealer'] as $bundle) {
  if (!FieldConfig::loadByName('node', $bundle, 'field_editorial_sections')) {
    FieldConfig::create([
      'entity_type' => 'node',
      'bundle' => $bundle,
      'field_name' => 'field_editorial_sections',
      'label' => 'Editorial Sections',
      'settings' => [
        'handler' => 'default:paragraph',
        'handler_settings' => ['target_bundles' => [
          'editorial_text' => 'editorial_text',
          'editorial_image' => 'editorial_image',
          'editorial_products' => 'editorial_products',
          'editorial_layout' => 'editorial_layout',
          'category_grid' => 'category_grid',
          'product_grid' => 'product_grid',
          'promo_banner' => 'promo_banner',
          'testimonial_slider' => 'testimonial_slider',
          'benefits_strip' => 'benefits_strip',
        ]],
      ],
    ])->save();
  }
  $form = \Drupal::service('entity_display.repository')->getFormDisplay('node', $bundle);
  $form->setComponent('field_editorial_sections', [
    'type' => 'layout_paragraphs',
    'weight' => 80,
    'settings' => ['preview_view_mode' => 'default', 'form_display_mode' => 'default'],
  ])->save();
  $view = \Drupal::service('entity_display.repository')->getViewDisplay('node', $bundle);
  $view->setComponent('field_editorial_sections', [
    'type' => 'layout_paragraphs',
    'weight' => 80,
    'label' => 'hidden',
    'settings' => ['view_mode' => 'default'],
  ])->save();
}

// The main navigation is generated from Product Category terms below.
if (!Block::load('terragear_main_navigation')) {
  Block::create([
    'id' => 'terragear_main_navigation',
    'theme' => 'terra_gear',
    'region' => 'primary_menu',
    'plugin' => 'system_menu_block:main',
    'settings' => ['label' => 'Main navigation', 'label_display' => FALSE, 'level' => 1, 'depth' => 2, 'expand_all_items' => TRUE],
    'visibility' => [],
    'weight' => 0,
  ])->save();
}
// The Standard profile ships a Home link; the approved header uses the logo.
\Drupal::service('plugin.manager.menu.link')->updateDefinition('standard.front_page', ['enabled' => FALSE]);
foreach ($manager->getStorage('block')->loadByProperties(['theme' => 'terra_gear']) as $block) {
  if ($block->id() === 'terragear_main_navigation' || $block->getPluginId() === 'system_main_block') {
    continue;
  }
  if ($block->getPluginId() === 'system_messages_block') {
    $block->setRegion('content')->save();
    continue;
  }
  $block->set('status', FALSE)->save();
}

$track_file = poc_find('file', ['uri' => 'public://demo-assets/rubber-track.png']);
$dealer_file = poc_find('file', ['uri' => 'public://demo-assets/dealer-storefront.png']);
if (!$track_file instanceof FileInterface || !$dealer_file instanceof FileInterface) {
  throw new RuntimeException('Expected existing public://demo-assets files. Run the original demo content setup first.');
}

// Reuse the supplied demo image for all temporary category and product cards.
$category_names = ['Rubber Tracks', 'Tires', 'Undercarriage Parts', 'Sprockets', 'Rollers', 'Idlers', 'Track Groups'];
$category_subtitles = [
  'Rubber Tracks' => 'Part No: TG Series', 'Tires' => 'Part No: TR Series',
  'Undercarriage Parts' => 'Parts and assemblies', 'Sprockets' => 'Part No: SP Series',
  'Rollers' => 'Part No: R Series', 'Idlers' => 'Part No: I Series',
  'Track Groups' => 'Part No: TG Group',
];
poc_field('taxonomy_term', 'product_category', 'field_category_card_subtitle', 'string', 'Card Subtitle');
$categories = [];
foreach ($category_names as $name) {
  $term = poc_find('taxonomy_term', ['vid' => 'product_category', 'name' => $name]);
  if (!$term) {
    $term = Term::create([
      'vid' => 'product_category',
      'name' => $name,
      'field_category_image' => ['target_id' => $track_file->id(), 'alt' => $name],
    ]);
    $term->save();
  }
  if ($term->get('field_category_card_subtitle')->isEmpty()) {
    $term->set('field_category_card_subtitle', $category_subtitles[$name])->save();
  }
  $categories[$name] = $term;
}
$all_products = poc_find('taxonomy_term', ['vid' => 'product_category', 'name' => 'All Products']);
if (!$all_products) {
  $all_products = Term::create([
    'vid' => 'product_category',
    'name' => 'All Products',
    'weight' => -100,
  ]);
  $all_products->save();
}
foreach ($categories as $term) {
  if ((int) $term->get('parent')->target_id !== (int) $all_products->id()) {
    $term->set('parent', ['target_id' => $all_products->id()])->save();
  }
}
$taxonomy_menu = TaxonomyMenu::load('product_categories');
if (!$taxonomy_menu) {
  $taxonomy_menu = TaxonomyMenu::create([
    'id' => 'product_categories',
    'label' => 'Product Categories',
    'vocabulary' => 'product_category',
    'menu' => 'main',
    'expanded' => TRUE,
    'depth' => 2,
    'menu_parent' => 'main:',
    'description_field_name' => '',
    'use_term_weight_order' => TRUE,
  ]);
}
else {
  $taxonomy_menu->set('menu_parent', 'main:');
}
$taxonomy_menu->save();
// Remove links from the older setup, including the duplicate utility actions.
foreach (['All Products', 'Find a Dealer', 'Resources', 'Support'] as $title) {
  foreach ($manager->getStorage('menu_link_content')->loadByProperties(['title' => $title, 'menu_name' => 'main']) as $link) {
    $link->delete();
  }
}

$existing_product = poc_find('node', ['type' => 'product', 'field_part_number' => 'TG450x86x55']);
$brand = poc_find('taxonomy_term', ['vid' => 'brand', 'name' => 'TerraGear']);
$alternate_brands = [];
foreach (['Premium', 'Economy'] as $brand_name) {
  $term = poc_find('taxonomy_term', ['vid' => 'brand', 'name' => $brand_name]);
  if (!$term) {
    $term = Term::create(['vid' => 'brand', 'name' => $brand_name]);
    $term->save();
  }
  $alternate_brands[$brand_name] = $term;
}
$machine_type = poc_find('taxonomy_term', ['vid' => 'machine_type', 'name' => 'Compact Track Loader']);
$machine_model = poc_find('taxonomy_term', ['vid' => 'machine_model', 'name' => 'Caterpillar 259D']);
$second_make = poc_find('taxonomy_term', ['vid' => 'machine_make', 'name' => 'Bobcat']);
if (!$second_make) {
  $second_make = Term::create(['vid' => 'machine_make', 'name' => 'Bobcat']);
  $second_make->save();
}
$second_model = poc_find('taxonomy_term', ['vid' => 'machine_model', 'name' => 'Bobcat T650']);
if (!$second_model) {
  $second_model = Term::create([
    'vid' => 'machine_model', 'name' => 'Bobcat T650',
    'field_machine_model_code' => 'T650',
    'field_machine_make' => ['target_id' => $second_make->id()],
    'field_machine_type' => ['target_id' => $machine_type->id()],
  ]);
  $second_model->save();
}
$product_rows = [
  ['TG320x86x49', 320, 86, 49, 'Rubber Tracks'],
  ['TG400x72x52', 400, 72, 52, 'Rubber Tracks'],
  ['TG500x90x58', 500, 90, 58, 'Rubber Tracks'],
  ['TG600x100x56', 600, 100, 56, 'Rubber Tracks'],
  ['TG230x52.5x84', 230, 52.5, 84, 'Rubber Tracks'],
  ['TG-SP320', 0, 0, 0, 'Sprockets'],
  ['TG-R8501', 0, 0, 0, 'Rollers'],
];
$all_products_nodes = [];
if ($existing_product) {
  $all_products_nodes[] = $existing_product;
}
foreach ($product_rows as [$part, $width, $pitch, $links, $category_name]) {
  $node = poc_find('node', ['type' => 'product', 'field_part_number' => $part]);
  if (!$node) {
    $values = [
      'type' => 'product',
      'title' => $part,
      'status' => 1,
      'uid' => 1,
      'field_part_number' => $part,
      'field_product_category' => ['target_id' => $categories[$category_name]->id()],
      'field_product_brand' => ['target_id' => in_array($part, ['TG320x86x49', 'TG500x90x58'], TRUE) ? $alternate_brands['Premium']->id() : ($part === 'TG230x52.5x84' ? $alternate_brands['Economy']->id() : $brand->id())],
      'field_product_image' => ['target_id' => $track_file->id(), 'alt' => $part . ' product image'],
      'field_product_gallery' => [
        ['target_id' => $track_file->id(), 'alt' => $part . ' view 1'],
        ['target_id' => $track_file->id(), 'alt' => $part . ' view 2'],
        ['target_id' => $track_file->id(), 'alt' => $part . ' view 3'],
      ],
      'field_product_short_description' => 'Engineered for dependable traction and long service life.',
      'field_product_description' => ['value' => 'A durable TerraGear part built to keep equipment moving on demanding jobs.', 'format' => 'basic_html'],
      'field_product_key_features' => [['value' => 'Durable design'], ['value' => 'Reliable performance'], ['value' => 'Built for demanding jobs']],
      'field_product_badge' => $part === 'TG400x72x52' ? 'new' : NULL,
      'field_width_mm' => $width ?: NULL,
      'field_pitch_mm' => $pitch ?: NULL,
      'field_links' => $links ?: NULL,
      'field_weight_kg' => $width ? (string) round($width * .17) : NULL,
      'field_primary_application' => ['target_id' => $machine_type->id()],
      'field_compatible_machines' => [['target_id' => in_array($part, ['TG320x86x49', 'TG500x90x58', 'TG-SP320'], TRUE) ? $second_model->id() : $machine_model->id()]],
    ];
    $node = Node::create($values);
    $node->save();
  }
  if (in_array($part, ['TG320x86x49', 'TG500x90x58', 'TG-SP320'], TRUE)
    && (int) $node->get('field_compatible_machines')->target_id !== (int) $second_model->id()) {
    $node->set('field_compatible_machines', [['target_id' => $second_model->id()]])->save();
  }
  $demo_brand = in_array($part, ['TG320x86x49', 'TG500x90x58'], TRUE) ? $alternate_brands['Premium'] : ($part === 'TG230x52.5x84' ? $alternate_brands['Economy'] : $brand);
  if ((int) $node->get('field_product_brand')->target_id !== (int) $demo_brand->id()) {
    $node->set('field_product_brand', ['target_id' => $demo_brand->id()])->save();
  }
  $all_products_nodes[] = $node;
}
foreach ($all_products_nodes as $product) {
  if ((int) $product->get('field_product_reviews')->status !== 2) {
    $product->set('field_product_reviews', ['status' => 2])->save();
  }
}

// Give the first product a useful gallery and a set of related products.
if ($existing_product && count($existing_product->get('field_product_gallery')) < 3) {
  $existing_product->set('field_product_gallery', [
    ['target_id' => $track_file->id(), 'alt' => 'Rubber track front view'],
    ['target_id' => $track_file->id(), 'alt' => 'Rubber track tread view'],
    ['target_id' => $track_file->id(), 'alt' => 'Rubber track side view'],
  ]);
  $existing_product->set('field_related_products', array_map(
    static fn ($product) => ['target_id' => $product->id()],
    array_slice($all_products_nodes, 1, 4)
  ));
  $existing_product->save();
}
foreach ($all_products_nodes as $product) {
  $changed = FALSE;
  if (count($product->get('field_product_gallery')) < 3) {
    $product->set('field_product_gallery', [
      ['target_id' => $track_file->id(), 'alt' => $product->label() . ' view 1'],
      ['target_id' => $track_file->id(), 'alt' => $product->label() . ' view 2'],
      ['target_id' => $track_file->id(), 'alt' => $product->label() . ' view 3'],
    ]);
    $changed = TRUE;
  }
  if ($product->get('field_related_products')->isEmpty()) {
    $category_id = (int) $product->get('field_product_category')->target_id;
    $related = array_values(array_filter($all_products_nodes, static fn ($candidate) =>
      $candidate->id() !== $product->id() && (int) $candidate->get('field_product_category')->target_id === $category_id));
    $product->set('field_related_products', array_map(static fn ($candidate) => ['target_id' => $candidate->id()], array_slice($related, 0, 4)));
    $changed = TRUE;
  }
  if ($changed) {
    $product->save();
  }
}
$pdp_banner = poc_find('block_content', ['type' => 'banner', 'info' => 'PDP equipment banner (Demo)']);
if (!$pdp_banner) {
  $promo_file = poc_find('file', ['uri' => 'public://demo-assets/promo-loader.png']);
  $pdp_banner = BlockContent::create([
    'type' => 'banner', 'info' => 'PDP equipment banner (Demo)', 'status' => 1, 'uid' => 1,
    'field_banner_eyebrow' => 'BUILT FOR DEMANDING JOBS',
    'field_banner_heading' => 'Built for demanding jobs',
    'field_banner_text' => ['value' => 'Reliable tracks for maximum uptime in agriculture and construction.', 'format' => 'plain_text'],
    'field_banner_image' => ['target_id' => $promo_file->id(), 'alt' => 'Loader working on a rugged site'],
  ]);
  $pdp_banner->save();
}
foreach ($all_products_nodes as $product) {
  if ($product->get('field_product_promo_banner')->isEmpty()) {
    $product->set('field_product_promo_banner', ['target_id' => $pdp_banner->id()])->save();
  }
}

// Homepage selections reference these existing entities.
$landing = poc_find('node', ['type' => 'landing_page']);
if ($landing) {
  $homepage_categories = array_map(static fn ($name) => $categories[$name], ['Rubber Tracks', 'Tires', 'Sprockets', 'Rollers', 'Idlers', 'Track Groups']);
  $homepage_products = array_map(static fn ($part) => poc_find('node', ['type' => 'product', 'field_part_number' => $part]),
    ['TG450x86x55', 'TG400x72x52', 'TG-SP320', 'TG-R8501', 'TG600x100x56']);
  $second_quote = poc_find('block_content', ['type' => 'testimonial', 'info' => 'Equipment operator quote (Demo)']);
  if (!$second_quote) {
    $second_quote = BlockContent::create([
      'type' => 'testimonial', 'info' => 'Equipment operator quote (Demo)', 'status' => 1, 'uid' => 1,
      'field_testimonial_quote' => ['value' => 'The replacement track fit our loader, and the local dealer made the process easy.', 'format' => 'plain_text'],
      'field_testimonial_person' => 'Demo Operator',
      'field_testimonial_role' => 'Equipment Operator',
      'field_testimonial_company' => 'Site Services',
    ]);
    $second_quote->save();
  }
  $landing->set('field_testimonials', [
    ['target_id' => poc_find('block_content', ['type' => 'testimonial', 'info' => 'Fleet manager quote (Demo)'])->id()],
    ['target_id' => $second_quote->id()],
  ]);
  if ($landing->get('field_popular_categories')->isEmpty() || count($landing->get('field_popular_categories')) === 7) {
    $landing->set('field_popular_categories', array_map(static fn ($term) => ['target_id' => $term->id()], $homepage_categories));
  }
  if ($landing->get('field_featured_products')->isEmpty()) {
    $landing->set('field_featured_products', array_map(static fn ($product) => ['target_id' => $product->id()], $homepage_products));
  }
  $landing->save();
  $section_types = array_map(static fn ($item) => $item->entity?->bundle(), iterator_to_array($landing->get('field_editorial_sections')));
  if (!in_array('category_grid', $section_types, TRUE)) {
    $landing->set('field_editorial_sections', []);
    $sections = [
      Paragraph::create(['type' => 'category_grid', 'field_heading' => 'Popular Categories',
        'field_categories' => array_map(static fn ($term) => ['target_id' => $term->id()], $homepage_categories)]),
      Paragraph::create(['type' => 'product_grid', 'field_heading' => 'Featured Products',
        'field_products' => array_map(static fn ($product) => ['target_id' => $product->id()], $homepage_products)]),
    ];
    $promo_block = $landing->get('field_promotional_banner')->entity;
    if ($promo_block) {
      $sections[] = Paragraph::create(['type' => 'promo_banner', 'field_banner' => ['target_id' => $promo_block->id()]]);
    }
    $testimonial_refs = array_map(static fn ($block) => ['target_id' => $block->id()], $landing->get('field_testimonials')->referencedEntities());
    if ($testimonial_refs) {
      $sections[] = Paragraph::create(['type' => 'testimonial_slider', 'field_heading' => 'Trusted by Professionals', 'field_testimonials' => $testimonial_refs]);
    }
    foreach ($sections as $paragraph) {
      $landing->get('field_editorial_sections')->appendItem(['entity' => $paragraph]);
    }
    $landing->save();
  }
  else {
    $kept_sections = [];
    foreach ($landing->get('field_editorial_sections')->referencedEntities() as $section) {
      if ($section->bundle() === 'benefits_strip') {
        continue;
      }
      $kept_sections[] = ['target_id' => $section->id(), 'target_revision_id' => $section->getRevisionId()];
      if ($section->bundle() === 'category_grid' && count($section->get('field_categories')) === 7) {
        $section->set('field_categories', array_map(static fn ($term) => ['target_id' => $term->id()], $homepage_categories))->save();
      }
      if ($section->bundle() === 'product_grid') {
        $current_parts = array_map(static fn ($product) => $product->get('field_part_number')->value, $section->get('field_products')->referencedEntities());
        if (in_array('TG320x86x49', $current_parts, TRUE)) {
          $section->set('field_products', array_map(static fn ($product) => ['target_id' => $product->id()], $homepage_products))->save();
        }
      }
      if ($section->bundle() === 'testimonial_slider' && count($section->get('field_testimonials')) < 2) {
        $section->set('field_testimonials', [
          ['target_id' => poc_find('block_content', ['type' => 'testimonial', 'info' => 'Fleet manager quote (Demo)'])->id()],
          ['target_id' => $second_quote->id()],
        ])->save();
      }
    }
    if (count($kept_sections) !== count($landing->get('field_editorial_sections'))) {
      $landing->set('field_editorial_sections', $kept_sections)->save();
    }
  }
}

// The three custom page routes have corresponding editable page records.
foreach (['Catalog Page', 'Dealer Finder Page'] as $title) {
  if (!poc_find('node', ['type' => 'landing_page', 'title' => $title])) {
    Node::create([
      'type' => 'landing_page', 'title' => $title, 'status' => 1, 'uid' => 1,
      'field_page_hero_heading' => $title === 'Catalog Page' ? 'Parts built for the long haul' : 'Authorized dealers. Real support.',
      'field_page_hero_text' => ['value' => $title === 'Catalog Page' ? 'Precision parts engineered for demanding jobs.' : 'Find a dealer near you and connect with local expertise.', 'format' => 'plain_text'],
    ])->save();
  }
  $page = poc_find('node', ['type' => 'landing_page', 'title' => $title]);
  if ($page && $page->get('field_page_hero_heading')->isEmpty()) {
    $page->set('field_page_hero_heading', $title === 'Catalog Page' ? 'Parts built for the long haul' : 'Authorized dealers. Real support.');
    $page->set('field_page_hero_text', ['value' => $title === 'Catalog Page' ? 'Precision parts engineered for demanding jobs.' : 'Find a dealer near you and connect with local expertise.', 'format' => 'plain_text']);
    $page->save();
  }
}
foreach ([
  'Catalog Page' => [
    ['Need help choosing a part?', 'Use the machine finder and technical filters to narrow the catalogue.'],
    ['Guaranteed fitment', 'Compare the specifications and check compatible machine models before choosing.'],
  ],
  'Dealer Finder Page' => [
    ['Local support', 'Contact a dealer for product advice and availability in your area.'],
    ['Get directions', 'Open the dealer location in Google Maps from the result card.'],
  ],
] as $title => $sections) {
  $page = poc_find('node', ['type' => 'landing_page', 'title' => $title]);
  if ($page && $page->get('field_editorial_sections')->isEmpty()) {
    foreach ($sections as [$heading, $body]) {
      $paragraph = Paragraph::create([
        'type' => 'editorial_text',
        'field_heading' => $heading,
        'field_text' => ['value' => $body, 'format' => 'plain_text'],
      ]);
      $page->get('field_editorial_sections')->appendItem(['entity' => $paragraph]);
    }
    $page->save();
  }
}

// Additional dealers reuse the one available dealership photograph.
$dealer_rows = [
  ['Tri-State Machinery', '5678 Progress Dr.', 'West Chester', 'OH', '45069', 39.3303, -84.4091],
  ['Capital Equipment', '9100 Commerce Blvd.', 'Mason', 'OH', '45040', 39.3601, -84.3099],
  ['Buckeye Heavy Equipment', '4455 Performance Way', 'Dayton', 'OH', '45424', 39.8439, -84.1247],
  ['Ohio Construction Supply', '7788 Service Rd.', 'Columbus', 'OH', '43228', 39.9564, -83.1224],
];
foreach ($dealer_rows as [$name, $street, $city, $state, $zip, $lat, $lon]) {
  if (!poc_find('node', ['type' => 'dealer', 'title' => $name])) {
    Node::create([
      'type' => 'dealer',
      'title' => $name,
      'status' => 1,
      'uid' => 1,
      'field_dealer_photo' => ['target_id' => $dealer_file->id(), 'alt' => $name . ' storefront'],
      'field_dealer_street_address' => $street,
      'field_dealer_city' => $city,
      'field_dealer_state' => $state,
      'field_dealer_zip_code' => $zip,
      'field_dealer_phone' => '+1 513 555 0100',
      'field_dealer_email' => 'contact@example.com',
      'field_dealer_latitude' => $lat,
      'field_dealer_longitude' => $lon,
    ])->save();
  }
}

// Editor-managed sample reviews; averages and counts are calculated at render.
foreach (array_slice($all_products_nodes, 0, 5) as $product) {
  $existing = \Drupal::entityQuery('comment')->accessCheck(FALSE)
    ->condition('entity_type', 'node')->condition('entity_id', $product->id())
    ->condition('field_name', 'field_product_reviews')->count()->execute();
  if ($existing) {
    continue;
  }
  foreach ([
    ['Reliable on demanding sites', 5, 'The track fits our loader and has performed consistently on rough ground.', 'Fleet Manager'],
    ['Good fit and traction', 4, 'Straightforward replacement and dependable traction so far.', 'Equipment Operator'],
  ] as [$subject, $rating, $body, $author]) {
    Comment::create([
      'comment_type' => 'product_review',
      'entity_type' => 'node',
      'entity_id' => $product->id(),
      'field_name' => 'field_product_reviews',
      'subject' => $subject,
      'comment_body' => ['value' => $body, 'format' => 'plain_text'],
      'field_rating' => $rating,
      'name' => $author,
      'uid' => 0,
      'status' => 1,
    ])->save();
  }
}

\Drupal::configFactory()->getEditable('frontend_editing.settings')
  ->set('entity_types', [
    'node' => ['landing_page', 'product', 'dealer'],
    'paragraph' => ['editorial_text', 'editorial_image', 'editorial_products', 'editorial_layout', 'category_grid', 'product_grid', 'promo_banner', 'testimonial_slider', 'benefits_strip'],
  ])
  ->set('primary_color', '#f4621c')
  ->save();
$editor_role = Role::load('catalog_editor');
if (!$editor_role) {
  $editor_role = Role::create(['id' => 'catalog_editor', 'label' => 'Catalogue Editor']);
}
$editor_role->grantPermission('access content overview');
$editor_role->grantPermission('access frontend editing');
$editor_role->grantPermission('add paragraphs');
$editor_role->grantPermission('move paragraphs');
$editor_role->grantPermission('delete paragraphs');
$editor_role->grantPermission('post comments');
$editor_role->grantPermission('skip comment approval');
foreach (['product', 'dealer', 'landing_page'] as $bundle) {
  $editor_role->grantPermission("create $bundle content");
  $editor_role->grantPermission("edit any $bundle content");
}
$editor_role->save();
\Drupal::configFactory()->getEditable('mercury_editor.settings')
  ->set('bundles', ['node' => ['landing_page' => 'landing_page', 'product' => 'product', 'dealer' => 'dealer']])
  ->set('edit_screen_theme', 'terra_gear')
  ->save();
\Drupal::configFactory()->getEditable('system.site')->set('page.front', '/home')->save();
\Drupal::configFactory()->getEditable('system.site')->set('name', 'TerraGear')->save();
\Drupal::configFactory()->getEditable('system.theme')->set('default', 'terra_gear')->save();

echo "TerraGear POC configuration and sample data are ready.\n";
