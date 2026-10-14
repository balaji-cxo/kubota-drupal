<?php

namespace Drupal\kubota_catalog;

use Drupal\Core\Url;
use Drupal\file\FileInterface;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\TermInterface;

/**
 * Converts catalogue entities into display data without copying their facts.
 */
final class CatalogData {

  /**
   * Builds a URL for a managed file.
   */
  public static function fileUrl(?FileInterface $file): string {
    return $file ? \Drupal::service('file_url_generator')->generateString($file->getFileUri()) : '';
  }

  /**
   * Returns a scalar field value, or an empty string.
   */
  public static function value($entity, string $field): string {
    return $entity->hasField($field) && !$entity->get($field)->isEmpty()
      ? (string) $entity->get($field)->value
      : '';
  }

  /**
   * Returns a linked document when the field has a file.
   */
  private static function document(NodeInterface $node, string $field, string $label): ?array {
    if (!$node->hasField($field) || $node->get($field)->isEmpty()) {
      return NULL;
    }
    $file = $node->get($field)->entity;
    return $file instanceof FileInterface
      ? ['label' => $label, 'url' => self::fileUrl($file)]
      : NULL;
  }

  /**
   * Product data for cards and full detail.
   */
  public static function product(NodeInterface $node, bool $full = FALSE): array {
    $image = $node->get('field_product_image')->entity;
    $category = $node->get('field_product_category')->entity;
    $application = $node->get('field_primary_application')->entity;
    $data = [
      'id' => (int) $node->id(),
      'title' => $node->label(),
      'part_number' => self::value($node, 'field_part_number'),
      'url' => $node->toUrl()->toString(),
      'image' => $image instanceof FileInterface ? self::fileUrl($image) : '',
      'image_alt' => $node->get('field_product_image')->alt ?: $node->label(),
      'category' => $category instanceof TermInterface ? $category->label() : '',
      'category_id' => $category instanceof TermInterface ? (int) $category->id() : 0,
      'badge' => self::value($node, 'field_product_badge'),
      'summary' => self::value($node, 'field_product_short_description'),
      'width' => self::value($node, 'field_width_mm'),
      'pitch' => self::value($node, 'field_pitch_mm'),
      'links' => self::value($node, 'field_links'),
      'weight' => self::value($node, 'field_weight_kg'),
      'application' => $application instanceof TermInterface ? $application->label() : '',
      'features' => array_column($node->get('field_product_key_features')->getValue(), 'value'),
    ];
    if (!$full) {
      return $data;
    }

    $data['description'] = self::value($node, 'field_product_description');
    $data['tread_pattern'] = self::value($node, 'field_tread_pattern');
    $data['material'] = self::value($node, 'field_material');
    $data['temperature_min'] = self::value($node, 'field_operating_temp_min_c');
    $data['temperature_max'] = self::value($node, 'field_operating_temp_max_c');
    $data['features'] = array_column($node->get('field_product_key_features')->getValue(), 'value');
    $data['oem_refs'] = array_column($node->get('field_oem_cross_references')->getValue(), 'value');
    $data['machines'] = [];
    $data['machine_ids'] = [];
    foreach ($node->get('field_compatible_machines')->referencedEntities() as $machine) {
      $data['machines'][] = $machine->label();
      $data['machine_ids'][] = (int) $machine->id();
    }
    $data['gallery'] = [$data['image']];
    foreach ($node->get('field_product_gallery')->referencedEntities() as $file) {
      if ($file instanceof FileInterface) {
        $data['gallery'][] = self::fileUrl($file);
      }
    }
    $data['documents'] = array_values(array_filter([
      self::document($node, 'field_document_datasheet', 'Datasheet (PDF)'),
      self::document($node, 'field_document_cad', 'CAD Drawing'),
      self::document($node, 'field_document_manual', 'Technical Manual (PDF)'),
      self::document($node, 'field_document_installation', 'Installation Guide (PDF)'),
    ]));
    $data['reviews'] = self::reviews($node);
    $data['promo'] = NULL;
    if ($node->hasField('field_product_promo_banner') && !$node->get('field_product_promo_banner')->isEmpty()) {
      $data['promo'] = self::banner($node->get('field_product_promo_banner')->entity);
    }
    return $data;
  }

  /**
   * Published Product Review comments and calculated summary.
   */
  public static function reviews(NodeInterface $node): array {
    if (!$node->hasField('field_product_reviews')) {
      return ['items' => [], 'count' => 0, 'average' => 0];
    }
    $ids = \Drupal::entityQuery('comment')
      ->accessCheck(TRUE)
      ->condition('entity_type', 'node')
      ->condition('entity_id', $node->id())
      ->condition('field_name', 'field_product_reviews')
      ->condition('status', 1)
      ->sort('created', 'DESC')
      ->execute();
    $comments = \Drupal::entityTypeManager()->getStorage('comment')->loadMultiple($ids);
    $items = [];
    $sum = 0;
    foreach ($comments as $comment) {
      $rating = (int) self::value($comment, 'field_rating');
      if ($rating < 1 || $rating > 5) {
        continue;
      }
      $sum += $rating;
      $items[] = [
        'author' => $comment->getAuthorName(),
        'rating' => $rating,
        'title' => $comment->getSubject(),
        'body' => self::value($comment, 'comment_body'),
      ];
    }
    $count = count($items);
    return [
      'items' => $items,
      'count' => $count,
      'average' => $count ? round($sum / $count, 1) : 0,
    ];
  }

  /**
   * Banner block fields.
   */
  public static function banner($block): ?array {
    if (!$block) {
      return NULL;
    }
    $image = $block->hasField('field_banner_image') ? $block->get('field_banner_image')->entity : NULL;
    $cta = $block->hasField('field_banner_cta') && !$block->get('field_banner_cta')->isEmpty()
      ? $block->get('field_banner_cta')->first() : NULL;
    return [
      'eyebrow' => self::value($block, 'field_banner_eyebrow'),
      'heading' => self::value($block, 'field_banner_heading'),
      'text' => self::value($block, 'field_banner_text'),
      'image' => $image instanceof FileInterface ? self::fileUrl($image) : '',
      'cta_url' => $cta ? $cta->getUrl()->toString() : '',
      'cta_title' => $cta ? $cta->title : '',
    ];
  }

  /**
   * Category term fields.
   */
  public static function category(TermInterface $term): array {
    $image = $term->hasField('field_category_image') ? $term->get('field_category_image')->entity : NULL;
    return [
      'id' => (int) $term->id(),
      'title' => $term->label(),
      'subtitle' => self::value($term, 'field_category_card_subtitle') ?: 'View products',
      'image' => $image instanceof FileInterface ? self::fileUrl($image) : '',
      'url' => Url::fromRoute('kubota_catalog.catalog', [], ['query' => ['category' => $term->id()]])->toString(),
    ];
  }

  /**
   * Dealer fields.
   */
  public static function dealer(NodeInterface $node): array {
    $image = $node->get('field_dealer_photo')->entity;
    return [
      'id' => (int) $node->id(),
      'title' => $node->label(),
      'image' => $image instanceof FileInterface ? self::fileUrl($image) : '',
      'street' => self::value($node, 'field_dealer_street_address'),
      'city' => self::value($node, 'field_dealer_city'),
      'state' => self::value($node, 'field_dealer_state'),
      'zip' => self::value($node, 'field_dealer_zip_code'),
      'phone' => self::value($node, 'field_dealer_phone'),
      'email' => self::value($node, 'field_dealer_email'),
      'latitude' => (float) self::value($node, 'field_dealer_latitude'),
      'longitude' => (float) self::value($node, 'field_dealer_longitude'),
    ];
  }

}
