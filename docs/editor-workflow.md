# TerraGear POC editor workflow

## Where the data lives

- **Product** (`/admin/content`): one Product node holds the part number, images, category, badge, specifications, compatible machines, documents, related Products, and its review thread. Its data appears in featured cards, catalogue cards, comparison, and the PDP. Edit the Product once to update those uses.
- **Product Category** (`/admin/structure/taxonomy/manage/product_category/overview`): **All Products** is the parent term. Its child terms supply the category cards and dropdown. Taxonomy Menu (`/admin/structure/taxonomy_menu/product_categories`) generates the parent and child links in Drupal's Main navigation menu. Add or reorder product navigation through this vocabulary; the header utility actions remain in the theme.
- **Landing Page**: `Home (Demo)`, `Catalog Page`, and `Dealer Finder Page` own the editable section areas on `/home`, `/catalog`, and `/dealers`. Editors can follow **Edit and arrange sections** on those pages. Mercury Editor and Layout Paragraphs provide add, edit, and drag to reorder. The homepage's Popular Categories, Featured Products, promo, benefits, and testimonial sections are Paragraphs. Category and Product cards reference existing entities; do not retype Product facts in a section.
- **Dealer** (`/admin/content`): Dealer nodes supply the name, photo, address, contact details, and coordinates used by the dealer finder. The map and cards use those records.
- **Product Review**: core Comment entities attach to a Product through `field_product_reviews`. Catalogue Editors can submit reviews; anonymous visitors have no comment posting permission. Only published reviews affect the displayed count and average.

## Common changes

1. **Edit a Product:** open its PDP and use the Drupal edit action, or open its node from `/admin/content`. The edit screen is Mercury Editor. Update the structured fields and save. Featured and related cards will read the updated record.
2. **Feature a Product:** edit `Home (Demo)`, open the Featured Product Grid section, and select the existing Product. Drag product references to set card order. Do not create a second Product for homepage use.
3. **Rearrange homepage sections:** open `/home` while logged in as a Catalogue Editor and select **Edit and arrange sections**. Drag the allowed sections, then save. The hero and machine finder stay at the top for navigation clarity.
4. **Add editorial content to catalogue or dealer finder:** use **Edit and arrange sections** on that page. Add Editorial Text, Editorial Image, or Curated Products. The catalogue result controls and dealer map/results are fixed data areas.
5. **Add a review:** with a Catalogue Editor account, use the Product's comment reply route (`/comment/reply/node/PRODUCT_ID/field_product_reviews`) and publish it. The PDP rating and count recalculate from published reviews. Test reviews are seeded by `scripts/configure-terragear-poc.php`.
6. **Edit a Dealer:** find its Dealer node at `/admin/content` and update address, phone, email, photo, or coordinates. The result card and Google marker use that record.
7. **Change colors:** Appearance → TerraGear → Settings → Color scheme. The default is Industrial gray and orange; Blueprint blue and white is available for comparison.

## Local setup and verification

Run `ddev drush php:script scripts/configure-terragear-poc.php`, `ddev drush cr`, and `ddev drush cex -y` after changing site configuration. The script is idempotent and reuses the existing images under `public://demo-assets`. Exported configuration is in `config/sync`.

The Google dealer map requires a Google Maps JavaScript API key with billing and appropriate referrer restrictions. The user will provide it later. Set `GOOGLE_MAPS_API_KEY` in the DDEV web container's local environment, then restart DDEV. Until then, the dealer list, sample ZIP 45242 distance search, result links, and a map placeholder work; road/satellite and markers activate with the key. Arbitrary ZIP geocoding is not yet implemented. The demo contact addresses and phone numbers are examples. Product document fields are ready for real files; the seeded Products do not include PDF downloads.

## Code map

```text
web/themes/custom/terra_gear/
  terra_gear.info.yml, .libraries.yml, .breakpoints.yml, .theme
  config/install/terra_gear.settings.yml
  config/schema/terra_gear.schema.yml
  css/terra-gear.css
  js/terra-gear.js, dealer-map.js
  components/{product-card,category-card,machine-finder,benefits-strip}/
  templates/layout/page.html.twig
  templates/navigation/menu--main.html.twig
  templates/content/node--product--full.html.twig
  templates/content/paragraph--*.html.twig

web/modules/custom/kubota_catalog/
  src/CatalogData.php
  src/Controller/PageController.php
  templates/kubota-{home,catalog,dealers,compare}.html.twig

scripts/configure-terragear-poc.php
config/sync/
```

The custom module owns entity queries, filtering, review aggregation, distance calculations, and route data. The theme owns markup, appearance settings, and small browser interactions. The existing `kubota_product` module retains its Product validation rule.
