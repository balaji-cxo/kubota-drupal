<?php

/**
 * Creates the Landing Page bundle and the reusable content it references.
 *
 * Run once with: ddev drush php:script scripts/create-landing-page-structure.php
 * Product entries can be selected after the Product content type is created.
 */

use Drupal\block_content\Entity\BlockContentType;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\NodeType;
use Drupal\taxonomy\Entity\Vocabulary;

// Do not alter an existing site structure accidentally.
foreach (['banner', 'benefits_strip', 'testimonial'] as $id) {
  if (BlockContentType::load($id)) {
    throw new RuntimeException("The $id custom block type already exists. No changes were made.");
  }
}
if (Vocabulary::load('product_category') || NodeType::load('landing_page')) {
  throw new RuntimeException('Landing Page or Product Category already exists. No changes were made.');
}

function add_landing_field(string $entity_type, string $bundle, string $name, array $definition, int $weight): void {
  $storage = [
    'field_name' => $name,
    'entity_type' => $entity_type,
    'type' => $definition['type'],
    'cardinality' => $definition['cardinality'] ?? 1,
    'settings' => $definition['storage_settings'] ?? [],
  ];
  FieldStorageConfig::create($storage)->save();

  $field = [
    'field_name' => $name,
    'entity_type' => $entity_type,
    'bundle' => $bundle,
    'label' => $definition['label'],
    'required' => $definition['required'] ?? FALSE,
    'description' => $definition['description'] ?? '',
    'settings' => $definition['field_settings'] ?? [],
  ];
  FieldConfig::create($field)->save();

  $repository = \Drupal::service('entity_display.repository');
  $form = $repository->getFormDisplay($entity_type, $bundle);
  $form->setComponent($name, [
    'type' => $definition['widget'],
    'weight' => $weight,
  ])->save();
  $view = $repository->getViewDisplay($entity_type, $bundle);
  $view->setComponent($name)->save();
}

function reference_field(string $label, string $target_type, string $target_bundle, bool $multiple = FALSE): array {
  return [
    'label' => $label,
    'type' => 'entity_reference',
    'widget' => 'entity_reference_autocomplete',
    'cardinality' => $multiple ? -1 : 1,
    'storage_settings' => ['target_type' => $target_type],
    'field_settings' => [
      'handler' => 'default:' . $target_type,
      'handler_settings' => [
        'target_bundles' => [$target_bundle => $target_bundle],
        'sort' => ['field' => '_none'],
        'auto_create' => FALSE,
      ],
    ],
  ];
}

foreach ([
  'banner' => 'Reusable hero or promotional banner.',
  'benefits_strip' => 'Four icon, heading and description benefits.',
  'testimonial' => 'A reusable customer quote.',
] as $id => $description) {
  BlockContentType::create([
    'id' => $id,
    'label' => ucwords(str_replace('_', ' ', $id)),
    'description' => $description,
    'revision' => TRUE,
  ])->save();
}

Vocabulary::create([
  'vid' => 'product_category',
  'name' => 'Product Category',
  'description' => 'Categories used by Products and the Popular Categories cards.',
])->save();

NodeType::create([
  'type' => 'landing_page',
  'name' => 'Landing Page',
  'description' => 'Curated landing pages composed of reusable banners and Product selections.',
  'new_revision' => TRUE,
  'preview_mode' => 0,
  'display_submitted' => FALSE,
])->save();
\Drupal::service('entity_field.manager')
  ->getBaseFieldDefinitions('node')['title']
  ->getConfig('landing_page')
  ->setLabel('Page title')
  ->save();

// Reusable banner. The benefits reference allows hero trust marks or a promo
// benefits grid to travel with the banner when it is reused elsewhere.
$banner_fields = [
  'field_banner_eyebrow' => ['label' => 'Eyebrow text', 'type' => 'string', 'widget' => 'string_textfield'],
  'field_banner_heading' => ['label' => 'Heading', 'type' => 'string', 'widget' => 'string_textfield', 'required' => TRUE],
  'field_banner_text' => ['label' => 'Supporting text', 'type' => 'text_long', 'widget' => 'text_textarea'],
  'field_banner_image' => ['label' => 'Desktop image', 'type' => 'image', 'widget' => 'image_image', 'required' => TRUE, 'field_settings' => ['file_directory' => 'banners']],
  'field_banner_mobile_image' => ['label' => 'Mobile image', 'type' => 'image', 'widget' => 'image_image', 'field_settings' => ['file_directory' => 'banners']],
  'field_banner_cta' => ['label' => 'Button', 'type' => 'link', 'widget' => 'link_default'],
  'field_banner_benefits' => reference_field('Banner benefits', 'block_content', 'benefits_strip'),
];
$weight = 1;
foreach ($banner_fields as $name => $definition) {
  add_landing_field('block_content', 'banner', $name, $definition, $weight++);
}

// Four fixed benefit slots match the approved UI and keep this core-only.
// Each icon/heading/text triplet stays together on the editor form.
$weight = 1;
for ($i = 1; $i <= 4; $i++) {
  add_landing_field('block_content', 'benefits_strip', "field_benefit_{$i}_icon", [
    'label' => "Benefit $i icon",
    'type' => 'image',
    'widget' => 'image_image',
    'field_settings' => ['file_directory' => 'benefits'],
  ], $weight++);
  add_landing_field('block_content', 'benefits_strip', "field_benefit_{$i}_heading", [
    'label' => "Benefit $i heading",
    'type' => 'string',
    'widget' => 'string_textfield',
  ], $weight++);
  add_landing_field('block_content', 'benefits_strip', "field_benefit_{$i}_text", [
    'label' => "Benefit $i text",
    'type' => 'string',
    'widget' => 'string_textfield',
  ], $weight++);
}

$testimonial_fields = [
  'field_testimonial_quote' => ['label' => 'Quote', 'type' => 'text_long', 'widget' => 'text_textarea', 'required' => TRUE],
  'field_testimonial_person' => ['label' => 'Person name', 'type' => 'string', 'widget' => 'string_textfield', 'required' => TRUE],
  'field_testimonial_role' => ['label' => 'Role', 'type' => 'string', 'widget' => 'string_textfield'],
  'field_testimonial_company' => ['label' => 'Company', 'type' => 'string', 'widget' => 'string_textfield'],
  'field_testimonial_portrait' => ['label' => 'Portrait', 'type' => 'image', 'widget' => 'image_image', 'field_settings' => ['file_directory' => 'testimonials']],
];
$weight = 1;
foreach ($testimonial_fields as $name => $definition) {
  add_landing_field('block_content', 'testimonial', $name, $definition, $weight++);
}

add_landing_field('taxonomy_term', 'product_category', 'field_category_image', [
  'label' => 'Category card image',
  'type' => 'image',
  'widget' => 'image_image',
  'field_settings' => ['file_directory' => 'product-categories'],
], 1);

$landing_fields = [
  'field_hero_banner' => reference_field('Hero banner', 'block_content', 'banner'),
  'field_popular_categories' => reference_field('Popular Categories', 'taxonomy_term', 'product_category', TRUE),
  'field_featured_products' => reference_field('Featured Products', 'node', 'product', TRUE),
  'field_promotional_banner' => reference_field('Promotional banner', 'block_content', 'banner'),
  'field_benefits_strip' => reference_field('Benefits strip', 'block_content', 'benefits_strip'),
  'field_testimonials' => reference_field('Testimonials', 'block_content', 'testimonial', TRUE),
];
$weight = 1;
foreach ($landing_fields as $name => $definition) {
  add_landing_field('node', 'landing_page', $name, $definition, $weight++);
}

echo "Created Landing Page with six reference fields, three reusable block types, and Product Category taxonomy.\n";
