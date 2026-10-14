<?php

/**
 * Creates Product fields and controlled terms for category, brand and fitment.
 *
 * Run once with:
 *   ddev drush en kubota_product -y
 *   ddev drush php:script scripts/create-product-content-type.php
 */

use Drupal\block_content\Entity\BlockContentType;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\NodeType;
use Drupal\taxonomy\Entity\Vocabulary;

if (!\Drupal::moduleHandler()->moduleExists('kubota_product')) {
  throw new RuntimeException('Enable the kubota_product module first to enforce unique part numbers.');
}
if (!Vocabulary::load('product_category') || !BlockContentType::load('banner')) {
  throw new RuntimeException('Create the Landing Page structure first; Product Category and Banner are required.');
}
if (NodeType::load('product')) {
  throw new RuntimeException('The Product content type already exists. No changes were made.');
}
foreach (['brand', 'machine_make', 'machine_type', 'machine_model'] as $id) {
  if (Vocabulary::load($id)) {
    throw new RuntimeException("The $id vocabulary already exists. No changes were made.");
  }
}

function add_product_field(string $entity_type, string $bundle, string $name, array $definition, int $weight): void {
  FieldStorageConfig::create([
    'field_name' => $name,
    'entity_type' => $entity_type,
    'type' => $definition['type'],
    'cardinality' => $definition['cardinality'] ?? 1,
    'settings' => $definition['storage_settings'] ?? [],
  ])->save();

  FieldConfig::create([
    'field_name' => $name,
    'entity_type' => $entity_type,
    'bundle' => $bundle,
    'label' => $definition['label'],
    'required' => $definition['required'] ?? FALSE,
    'description' => $definition['description'] ?? '',
    'settings' => $definition['field_settings'] ?? [],
  ])->save();

  $repository = \Drupal::service('entity_display.repository');
  $repository->getFormDisplay($entity_type, $bundle)
    ->setComponent($name, [
      'type' => $definition['widget'],
      'weight' => $weight,
    ])->save();
  $repository->getViewDisplay($entity_type, $bundle)
    ->setComponent($name)->save();
}

function product_reference(string $label, string $target_type, string $target_bundle, bool $multiple = FALSE): array {
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
  'brand' => ['Brand', 'Brand choices for the Product filter.'],
  'machine_make' => ['Machine Make', 'Manufacturers used by machine compatibility.'],
  'machine_type' => ['Machine Type', 'Machine types and Product applications.'],
  'machine_model' => ['Machine Model', 'A model with its make and machine type.'],
] as $id => [$name, $description]) {
  Vocabulary::create([
    'vid' => $id,
    'name' => $name,
    'description' => $description,
  ])->save();
}

NodeType::create([
  'type' => 'product',
  'name' => 'Product',
  'description' => 'Parts displayed on homepage cards, product listings and product details.',
  'new_revision' => TRUE,
  'preview_mode' => 0,
  'display_submitted' => FALSE,
])->save();
\Drupal::service('entity_field.manager')
  ->getBaseFieldDefinitions('node')['title']
  ->getConfig('product')
  ->setLabel('Product name')
  ->save();

// Use the full term name for clear autocomplete choices, e.g. "Caterpillar
// 259D". The model code is stored separately for the frontend selector.
$machine_fields = [
  'field_machine_model_code' => [
    'label' => 'Model code',
    'type' => 'string',
    'widget' => 'string_textfield',
    'required' => TRUE,
    'description' => 'The value shown in the Model selector, for example 259D.',
  ],
  'field_machine_make' => product_reference('Make', 'taxonomy_term', 'machine_make'),
  'field_machine_type' => product_reference('Machine type', 'taxonomy_term', 'machine_type'),
];
$machine_fields['field_machine_make']['required'] = TRUE;
$machine_fields['field_machine_type']['required'] = TRUE;
$weight = 1;
foreach ($machine_fields as $name => $definition) {
  add_product_field('taxonomy_term', 'machine_model', $name, $definition, $weight++);
}

$product_fields = [
  'field_part_number' => [
    'label' => 'Part number',
    'type' => 'string',
    'widget' => 'string_textfield',
    'required' => TRUE,
    'storage_settings' => ['max_length' => 128],
    'description' => 'Unique product identifier used by Search by Part Number.',
  ],
  'field_product_category' => product_reference('Product Category', 'taxonomy_term', 'product_category'),
  'field_product_brand' => product_reference('Brand', 'taxonomy_term', 'brand'),
  'field_product_image' => [
    'label' => 'Primary image',
    'type' => 'image',
    'widget' => 'image_image',
    'required' => TRUE,
    'field_settings' => ['file_directory' => 'products'],
  ],
  'field_product_short_description' => [
    'label' => 'Short description',
    'type' => 'string_long',
    'widget' => 'string_textarea',
  ],
  'field_product_badge' => [
    'label' => 'Badge',
    'type' => 'list_string',
    'widget' => 'options_select',
    'storage_settings' => ['allowed_values' => ['new' => 'New', 'best_seller' => 'Best Seller']],
  ],
  'field_product_gallery' => [
    'label' => 'Additional gallery images',
    'type' => 'image',
    'widget' => 'image_image',
    'cardinality' => -1,
    'field_settings' => ['file_directory' => 'products/gallery'],
  ],
  'field_product_description' => [
    'label' => 'Full description',
    'type' => 'text_long',
    'widget' => 'text_textarea',
  ],
  'field_product_key_features' => [
    'label' => 'Key features',
    'type' => 'string',
    'widget' => 'string_textfield',
    'cardinality' => -1,
  ],
  'field_width_mm' => [
    'label' => 'Width (mm)',
    'type' => 'decimal',
    'widget' => 'number',
    'storage_settings' => ['precision' => 10, 'scale' => 2],
    'field_settings' => ['min' => 0],
    'description' => 'Rubber track specification. Store millimetres as a number for PLP range filters.',
  ],
  'field_pitch_mm' => [
    'label' => 'Pitch (mm)',
    'type' => 'decimal',
    'widget' => 'number',
    'storage_settings' => ['precision' => 10, 'scale' => 2],
    'field_settings' => ['min' => 0],
    'description' => 'Rubber track specification.',
  ],
  'field_links' => [
    'label' => 'Number of links',
    'type' => 'integer',
    'widget' => 'number',
    'field_settings' => ['min' => 0],
    'description' => 'Rubber track specification.',
  ],
  'field_weight_kg' => [
    'label' => 'Weight (kg)',
    'type' => 'decimal',
    'widget' => 'number',
    'storage_settings' => ['precision' => 10, 'scale' => 2],
    'field_settings' => ['min' => 0],
  ],
  'field_tread_pattern' => ['label' => 'Tread pattern', 'type' => 'string', 'widget' => 'string_textfield'],
  'field_material' => ['label' => 'Material', 'type' => 'string', 'widget' => 'string_textfield'],
  'field_operating_temp_min_c' => [
    'label' => 'Minimum operating temperature (°C)',
    'type' => 'decimal',
    'widget' => 'number',
    'storage_settings' => ['precision' => 6, 'scale' => 2],
  ],
  'field_operating_temp_max_c' => [
    'label' => 'Maximum operating temperature (°C)',
    'type' => 'decimal',
    'widget' => 'number',
    'storage_settings' => ['precision' => 6, 'scale' => 2],
  ],
  'field_primary_application' => product_reference('Primary application', 'taxonomy_term', 'machine_type'),
  'field_compatible_machines' => product_reference('Compatible machine models', 'taxonomy_term', 'machine_model', TRUE),
  'field_document_datasheet' => [
    'label' => 'Datasheet (PDF)',
    'type' => 'file',
    'widget' => 'file_generic',
    'field_settings' => ['file_directory' => 'products/documents', 'file_extensions' => 'pdf'],
  ],
  'field_document_cad' => [
    'label' => 'CAD drawing',
    'type' => 'file',
    'widget' => 'file_generic',
    'field_settings' => ['file_directory' => 'products/documents', 'file_extensions' => 'dwg dxf'],
  ],
  'field_document_manual' => [
    'label' => 'Technical manual (PDF)',
    'type' => 'file',
    'widget' => 'file_generic',
    'field_settings' => ['file_directory' => 'products/documents', 'file_extensions' => 'pdf'],
  ],
  'field_document_installation' => [
    'label' => 'Installation guide (PDF)',
    'type' => 'file',
    'widget' => 'file_generic',
    'field_settings' => ['file_directory' => 'products/documents', 'file_extensions' => 'pdf'],
  ],
  'field_oem_cross_references' => [
    'label' => 'OEM cross-references',
    'type' => 'string',
    'widget' => 'string_textfield',
    'cardinality' => -1,
    'description' => 'One OEM part reference per entry.',
  ],
  'field_product_promo_banner' => product_reference('PDP promotional banner', 'block_content', 'banner'),
  'field_related_products' => product_reference('Related Products', 'node', 'product', TRUE),
];

$product_fields['field_product_category']['required'] = TRUE;
$product_fields['field_product_brand']['required'] = TRUE;
$weight = 1;
foreach ($product_fields as $name => $definition) {
  add_product_field('node', 'product', $name, $definition, $weight++);
}

// The Landing Page field was created before Product existed. Resaving records
// its new bundle dependency, while retaining the Product-only selection rule.
FieldConfig::loadByName('node', 'landing_page', 'field_featured_products')->save();

echo 'Created Product with ' . count($product_fields) . " fields, four vocabularies and unique part-number validation.\n";
