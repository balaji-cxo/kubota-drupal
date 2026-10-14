<?php

namespace Drupal\kubota_catalog\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\kubota_catalog\CatalogData;
use Drupal\node\NodeInterface;
use Drupal\file\FileInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Public catalogue and dealer pages.
 */
final class PageController extends ControllerBase {

  /**
   * Loads published nodes of a bundle.
   *
   * @return \Drupal\node\NodeInterface[]
   *   Published nodes.
   */
  private function nodes(string $bundle): array {
    $ids = $this->entityTypeManager()->getStorage('node')->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', $bundle)
      ->condition('status', 1)
      ->sort('created', 'DESC')
      ->execute();
    return $this->entityTypeManager()->getStorage('node')->loadMultiple($ids);
  }

  /**
   * Machine selector options.
   */
  private function machines(): array {
    $storage = $this->entityTypeManager()->getStorage('taxonomy_term');
    $data = [];
    foreach (['machine_make' => 'makes', 'machine_type' => 'types', 'machine_model' => 'models'] as $vid => $key) {
      $ids = $storage->getQuery()->accessCheck(TRUE)->condition('vid', $vid)->sort('name')->execute();
      $data[$key] = [];
      foreach ($storage->loadMultiple($ids) as $term) {
        $data[$key][] = [
          'id' => (int) $term->id(),
          'label' => $vid === 'machine_model' && $term->hasField('field_machine_model_code')
            ? CatalogData::value($term, 'field_machine_model_code') : $term->label(),
          'make' => $term->hasField('field_machine_make') ? (int) $term->get('field_machine_make')->target_id : 0,
          'type' => $term->hasField('field_machine_type') ? (int) $term->get('field_machine_type')->target_id : 0,
        ];
      }
    }
    return $data;
  }

  /**
   * Select options for catalogue taxonomy filters.
   */
  private function terms(string $vocabulary): array {
    $storage = $this->entityTypeManager()->getStorage('taxonomy_term');
    $ids = $storage->getQuery()->accessCheck(TRUE)->condition('vid', $vocabulary)->sort('name')->execute();
    return array_map(static fn ($term): array => ['id' => (int) $term->id(), 'label' => $term->label()], array_values($storage->loadMultiple($ids)));
  }

  /**
   * Reuses one existing demo asset for non-product hero artwork.
   */
  private function demoImage(string $name): string {
    $files = $this->entityTypeManager()->getStorage('file')->loadByProperties(['uri' => 'public://demo-assets/' . $name]);
    $file = reset($files);
    return $file instanceof FileInterface ? CatalogData::fileUrl($file) : '';
  }

  /**
   * Renders optional editor-arranged paragraphs for a content record.
   */
  private function editorialSections(?NodeInterface $node): array {
    if (!$node || !$node->hasField('field_editorial_sections') || $node->get('field_editorial_sections')->isEmpty()) {
      return [];
    }
    $build = $this->entityTypeManager()->getViewBuilder('node')->viewField($node->get('field_editorial_sections'));
    $build['#label_display'] = 'hidden';
    return $build;
  }

  /**
   * Finds the page record that owns an editorial zone on a custom route.
   */
  private function pageRecord(string $title): ?NodeInterface {
    foreach ($this->nodes('landing_page') as $node) {
      if ($node->label() === $title) {
        return $node;
      }
    }
    return NULL;
  }

  /**
   * The front page.
   */
  public function home(): array {
    $landing_pages = $this->nodes('landing_page');
    $landing = NULL;
    foreach ($landing_pages as $candidate) {
      if ($candidate->label() === 'Home (Demo)') {
        $landing = $candidate;
        break;
      }
    }
    $landing ??= reset($landing_pages) ?: NULL;
    $hero = NULL;
    $promo = NULL;
    $categories = [];
    $products = [];
    $testimonials = [];
    if ($landing instanceof NodeInterface) {
      $hero = CatalogData::banner($landing->get('field_hero_banner')->entity);
      $promo = CatalogData::banner($landing->get('field_promotional_banner')->entity);
      foreach ($landing->get('field_popular_categories')->referencedEntities() as $term) {
        $categories[] = CatalogData::category($term);
      }
      foreach ($landing->get('field_featured_products')->referencedEntities() as $node) {
        if ($node instanceof NodeInterface && $node->isPublished()) {
          $products[] = CatalogData::product($node);
        }
      }
      foreach ($landing->get('field_testimonials')->referencedEntities() as $block) {
        $testimonials[] = [
          'quote' => CatalogData::value($block, 'field_testimonial_quote'),
          'person' => CatalogData::value($block, 'field_testimonial_person'),
          'role' => CatalogData::value($block, 'field_testimonial_role'),
          'company' => CatalogData::value($block, 'field_testimonial_company'),
        ];
      }
    }
    return [
      '#theme' => 'kubota_home',
      '#page_data' => [
        'hero' => $hero,
        'promo' => $promo,
        'categories' => $categories,
        'products' => $products,
        'testimonials' => $testimonials,
        'machines' => $this->machines(),
        'editorial_sections' => $this->editorialSections($landing),
        'edit_url' => $landing && $landing->access('update') ? $landing->toUrl('edit-form')->toString() : '',
      ],
      '#cache' => ['tags' => ['node_list', 'taxonomy_term_list', 'block_content_list']],
    ];
  }

  /**
   * All products and category listing.
   */
  public function catalog(Request $request): array {
    $page_record = $this->pageRecord('Catalog Page');
    $category_id = (int) $request->query->get('category', 0);
    $search = trim((string) $request->query->get('q', ''));
    $make = (int) $request->query->get('make', 0);
    $type = (int) $request->query->get('type', 0);
    $model = (int) $request->query->get('model', 0);
    $brand = (int) $request->query->get('brand', 0);
    $application = (int) $request->query->get('application', 0);
    $sort = (string) $request->query->get('sort', 'part');

    $category_storage = $this->entityTypeManager()->getStorage('taxonomy_term');
    $category_ids = $category_storage->getQuery()->accessCheck(TRUE)
      ->condition('vid', 'product_category')->sort('name')->execute();
    $categories = [];
    $active_category = NULL;
    foreach ($category_storage->loadMultiple($category_ids) as $term) {
      if ($term->label() === 'All Products') {
        continue;
      }
      $categories[] = CatalogData::category($term);
      if ((int) $term->id() === $category_id) {
        $active_category = $term;
      }
    }

    $all = [];
    foreach ($this->nodes('product') as $node) {
      if ($category_id && (int) $node->get('field_product_category')->target_id !== $category_id) {
        continue;
      }
      if ($brand && (int) $node->get('field_product_brand')->target_id !== $brand) {
        continue;
      }
      if ($application && (int) $node->get('field_primary_application')->target_id !== $application) {
        continue;
      }
      if ($search && stripos($node->label() . ' ' . CatalogData::value($node, 'field_part_number'), $search) === FALSE) {
        continue;
      }
      if ($model) {
        $machine_ids = array_column($node->get('field_compatible_machines')->getValue(), 'target_id');
        if (!in_array($model, $machine_ids)) {
          continue;
        }
      }
      elseif ($make || $type) {
        $fits = FALSE;
        foreach ($node->get('field_compatible_machines')->referencedEntities() as $machine) {
          if ((!$make || (int) $machine->get('field_machine_make')->target_id === $make)
            && (!$type || (int) $machine->get('field_machine_type')->target_id === $type)) {
            $fits = TRUE;
            break;
          }
        }
        if (!$fits) {
          continue;
        }
      }
      $data = CatalogData::product($node);
      $in_range = TRUE;
      foreach (['width', 'pitch', 'links'] as $field) {
        $min = $request->query->get($field . '_min');
        $max = $request->query->get($field . '_max');
        $value = (float) $data[$field];
        if (($min !== NULL && $min !== '' && $value < (float) $min)
          || ($max !== NULL && $max !== '' && $value > (float) $max)) {
          $in_range = FALSE;
        }
      }
      if ($in_range) {
        $all[] = $data;
      }
    }
    usort($all, static function (array $a, array $b) use ($sort): int {
      return match ($sort) {
        'width_asc' => ((float) $a['width'] <=> (float) $b['width']),
        'width_desc' => ((float) $b['width'] <=> (float) $a['width']),
        'title' => strcasecmp($a['title'], $b['title']),
        default => strcasecmp($a['part_number'], $b['part_number']),
      };
    });

    return [
      '#theme' => 'kubota_catalog',
      '#page_data' => [
        'title' => $active_category ? $active_category->label() : 'All Products',
        'description' => $active_category ? $active_category->getDescription() : 'Precision parts engineered for demanding jobs.',
        'hero_heading' => $page_record ? CatalogData::value($page_record, 'field_page_hero_heading') : 'Parts built for the long haul',
        'hero_text' => $page_record ? CatalogData::value($page_record, 'field_page_hero_text') : 'Precision parts engineered for demanding jobs.',
        'hero_image' => $this->demoImage('promo-loader.png'),
        'products' => $all,
        'count' => count($all),
        'categories' => $categories,
        'active_category' => $category_id,
        'filters' => $request->query->all(),
        'machines' => $this->machines(),
        'brands' => $this->terms('brand'),
        'applications' => $this->terms('machine_type'),
        'editorial_sections' => $this->editorialSections($page_record),
        'edit_url' => $page_record && $page_record->access('update') ? $page_record->toUrl('edit-form')->toString() : '',
      ],
      '#cache' => ['contexts' => ['url.query_args'], 'tags' => ['node_list', 'taxonomy_term_list']],
    ];
  }

  /**
   * Great-circle distance in miles.
   */
  private static function miles(float $lat1, float $lon1, float $lat2, float $lon2): float {
    $a = sin(deg2rad($lat2 - $lat1) / 2) ** 2
      + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
      * sin(deg2rad($lon2 - $lon1) / 2) ** 2;
    return 3958.8 * 2 * atan2(sqrt($a), sqrt(1 - $a));
  }

  /**
   * Dealer finder.
   */
  public function dealers(Request $request): array {
    $page_record = $this->pageRecord('Dealer Finder Page');
    $zip = trim((string) $request->query->get('zip', '45242'));
    $radius = (int) $request->query->get('radius', 100);
    $radius = in_array($radius, [25, 50, 100, 250], TRUE) ? $radius : 100;
    $dealers = array_map([CatalogData::class, 'dealer'], $this->nodes('dealer'));
    $center = NULL;
    // The mockup ZIP works before a Google geocoding key is available.
    $sample_zip_centers = ['45242' => [39.2486, -84.3816]];
    $center = $sample_zip_centers[$zip] ?? NULL;
    foreach ($dealers as $dealer) {
      if (!$center && $zip && $dealer['zip'] === $zip) {
        $center = [$dealer['latitude'], $dealer['longitude']];
        break;
      }
    }
    if ($center) {
      foreach ($dealers as &$dealer) {
        $dealer['distance'] = round(self::miles($center[0], $center[1], $dealer['latitude'], $dealer['longitude']), 1);
      }
      unset($dealer);
      $dealers = array_values(array_filter($dealers, static fn (array $dealer): bool => $dealer['distance'] <= $radius));
      usort($dealers, static fn (array $a, array $b): int => $a['distance'] <=> $b['distance']);
    }
    elseif ($zip) {
      $dealers = array_values(array_filter($dealers, static fn (array $dealer): bool =>
        stripos($dealer['zip'] . ' ' . $dealer['city'] . ' ' . $dealer['state'], $zip) !== FALSE
      ));
    }
    $dealer_sort = (string) $request->query->get('sort', $center ? 'distance' : 'name');
    if ($dealer_sort === 'name') {
      usort($dealers, static fn (array $a, array $b): int => strcasecmp($a['title'], $b['title']));
    }
    elseif ($dealer_sort === 'distance' && $center) {
      usort($dealers, static fn (array $a, array $b): int => ($a['distance'] ?? 0) <=> ($b['distance'] ?? 0));
    }
    $selected = NULL;
    $product_id = (int) $request->query->get('product', 0);
    if ($product_id) {
      $node = $this->entityTypeManager()->getStorage('node')->load($product_id);
      if ($node instanceof NodeInterface && $node->bundle() === 'product' && $node->isPublished()) {
        $selected = CatalogData::product($node);
      }
    }
    return [
      '#theme' => 'kubota_dealers',
      '#page_data' => [
        'dealers' => $dealers,
        'count' => count($dealers),
        'zip' => $zip,
        'radius' => $radius,
        'sort' => $dealer_sort,
        'product' => $selected,
        'machines' => $this->machines(),
        'google_key' => getenv('GOOGLE_MAPS_API_KEY') ?: '',
        'hero_heading' => $page_record ? CatalogData::value($page_record, 'field_page_hero_heading') : 'Authorized dealers. Real support.',
        'hero_text' => $page_record ? CatalogData::value($page_record, 'field_page_hero_text') : 'Find a dealer near you and connect with local expertise.',
        'hero_image' => $this->demoImage('home-hero.png'),
        'catalog_url' => Url::fromRoute('kubota_catalog.catalog')->toString(),
        'editorial_sections' => $this->editorialSections($page_record),
        'edit_url' => $page_record && $page_record->access('update') ? $page_record->toUrl('edit-form')->toString() : '',
      ],
      '#attached' => ['library' => ['terra_gear/dealer-map']],
      '#cache' => ['contexts' => ['url.query_args'], 'max-age' => 0],
    ];
  }

  /**
   * Product comparison page.
   */
  public function compare(Request $request): array {
    $ids = array_slice(array_unique(array_filter(array_map('intval', explode(',', (string) $request->query->get('ids', ''))))), 0, 4);
    $products = [];
    foreach ($ids as $id) {
      $node = $this->entityTypeManager()->getStorage('node')->load($id);
      if ($node instanceof NodeInterface && $node->bundle() === 'product' && $node->isPublished()) {
        $products[] = CatalogData::product($node, TRUE);
      }
    }
    return [
      '#theme' => 'kubota_compare',
      '#page_data' => ['products' => $products],
      '#cache' => ['contexts' => ['url.query_args'], 'tags' => ['node_list']],
    ];
  }

}
