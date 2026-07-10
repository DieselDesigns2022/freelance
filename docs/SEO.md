# SEO Documentation

## Implemented SEO

### Metadata

Implemented: Public pages set `$pageTitle` and `$metaDescription`, which are rendered in `includes/header.php` as `<title>` and meta description.

### SEO Title Fallback

Implemented: `project.php` uses `seo_title` when present and falls back to the project title.

### SEO Description Fallback

Implemented: `project.php` uses `seo_description` when present and falls back to `short_description`.

### Project Detail Metadata

Implemented: Project detail pages set page-specific title and description values before including the public header.

### Public Portfolio Listing Pages

Implemented: `websites.php` and `shopify-makeovers.php` have page-specific titles, meta descriptions, headings, intro text, and internal links to detail pages.

### Internal Linking

Implemented:

- Homepage links to Website Builds and Shopify Make-Overs.
- Project cards link to project detail pages.
- Project cards and detail pages link to live websites when provided.
- Header navigation links to main portfolio sections.

## Not Implemented

- Canonical tags are not implemented.
- Open Graph metadata is not implemented.
- Twitter Card metadata is not implemented.
- XML sitemap is not implemented.
- `robots.txt` is not implemented.
- General portfolio/project structured data is not implemented. FAQPage JSON-LD is implemented for visible published FAQs.
- Pretty URLs are not implemented.

## Future SEO Roadmap

- Add canonical URLs.
- Add Open Graph and Twitter Card metadata.
- Add project thumbnail social preview tags.
- Add sitemap generation.
- Add `robots.txt`.
- Add structured data for portfolio/project pages.
- Consider pretty URLs such as `/project/project-slug` if routing is expanded.
- Add image width/height attributes or generated responsive image variants.

## Service Expansion SEO

Implemented:

- Homepage title updated to `Website Builds & Revamps by Diesel Designs`.
- New public pages use unique titles and meta descriptions for Services, Portfolio, FAQ, and Request pages.
- Public navigation now links to Home, Services, Portfolio, Website Builds, Shopify Make-Overs, FAQ, Request a Website, and Contact.
- Services content uses natural language around website builds, website revamps, Shopify make-overs, small business websites, digital shop websites, visual website refreshes, and SEO-friendly website foundations.
- FAQ page outputs FAQPage JSON-LD only for visible published FAQs.

Future roadmap:

- Add canonical tags and Open Graph metadata.
- Add sitemap and robots.txt.

## Phase 2 SEO Notes

The store page has a dedicated page title and meta description. Product detail pages build titles and meta descriptions from active product data and include simple Service structured data with provider and offer price.

Signing and contract-copy token routes are marked `noindex, nofollow` because they contain private order/contract information. Admin pages remain private and should not be indexed.

No fake reviews, ratings, or availability claims are added to structured data.
