<?php

/**
 * Creates the Dealer content type and its backend fields.
 *
 * Run once with:
 *   ddev drush en telephone -y
 *   ddev drush php:script scripts/create-dealer-content-type.php
 */

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\NodeType;

if (NodeType::load('dealer')) {
  throw new RuntimeException('The Dealer content type already exists. No changes were made.');
}

$type = NodeType::create([
  'type' => 'dealer',
  'name' => 'Dealer',
  'description' => 'Dealer locations used by the Where to Buy finder.',
  'new_revision' => TRUE,
  'preview_mode' => 0,
  'display_submitted' => FALSE,
]);
$type->save();

// The title label is a bundle-specific base field override, not a node type
// setting.
\Drupal::service('entity_field.manager')
  ->getBaseFieldDefinitions('node')['title']
  ->getConfig('dealer')
  ->setLabel('Dealer name')
  ->save();

$fields = [
  'field_dealer_photo' => [
    'label' => 'Dealer photo',
    'type' => 'image',
    'required' => TRUE,
    'widget' => 'image_image',
    'field_settings' => ['file_directory' => 'dealers'],
  ],
  'field_dealer_street_address' => [
    'label' => 'Street address',
    'type' => 'string',
    'required' => TRUE,
    'widget' => 'string_textfield',
  ],
  'field_dealer_address_line_2' => [
    'label' => 'Address line 2',
    'type' => 'string',
    'required' => FALSE,
    'widget' => 'string_textfield',
  ],
  'field_dealer_city' => [
    'label' => 'City',
    'type' => 'string',
    'required' => TRUE,
    'widget' => 'string_textfield',
  ],
  'field_dealer_state' => [
    'label' => 'State',
    'type' => 'string',
    'required' => TRUE,
    'widget' => 'string_textfield',
  ],
  'field_dealer_zip_code' => [
    'label' => 'ZIP code',
    'type' => 'string',
    'required' => TRUE,
    'widget' => 'string_textfield',
  ],
  'field_dealer_phone' => [
    'label' => 'Phone number',
    'type' => 'telephone',
    'required' => TRUE,
    'widget' => 'telephone_default',
  ],
  'field_dealer_email' => [
    'label' => 'Email address',
    'type' => 'email',
    'required' => TRUE,
    'widget' => 'email_default',
  ],
  'field_dealer_latitude' => [
    'label' => 'Latitude',
    'type' => 'decimal',
    'required' => TRUE,
    'widget' => 'number',
    'storage_settings' => ['precision' => 10, 'scale' => 7],
    'field_settings' => ['min' => -90, 'max' => 90],
    'description' => 'Decimal degrees, from -90 to 90.',
  ],
  'field_dealer_longitude' => [
    'label' => 'Longitude',
    'type' => 'decimal',
    'required' => TRUE,
    'widget' => 'number',
    'storage_settings' => ['precision' => 10, 'scale' => 7],
    'field_settings' => ['min' => -180, 'max' => 180],
    'description' => 'Decimal degrees, from -180 to 180.',
  ],
];

$form_display = \Drupal::service('entity_display.repository')->getFormDisplay('node', 'dealer');
$view_display = \Drupal::service('entity_display.repository')->getViewDisplay('node', 'dealer');
$weight = 1;

foreach ($fields as $name => $definition) {
  FieldStorageConfig::create([
    'field_name' => $name,
    'entity_type' => 'node',
    'type' => $definition['type'],
    'cardinality' => 1,
    'settings' => $definition['storage_settings'] ?? [],
  ])->save();

  FieldConfig::create([
    'field_name' => $name,
    'entity_type' => 'node',
    'bundle' => 'dealer',
    'label' => $definition['label'],
    'required' => $definition['required'],
    'description' => $definition['description'] ?? '',
    'settings' => $definition['field_settings'] ?? [],
  ])->save();

  $form_display->setComponent($name, [
    'type' => $definition['widget'],
    'weight' => $weight++,
  ]);
  $view_display->setComponent($name);
}

$form_display->save();
$view_display->save();

echo "Created Dealer content type with " . count($fields) . " fields plus the built-in Dealer name and Published status.\n";
