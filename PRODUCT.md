# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

Mostly individual shoppers buying one to five bags for themselves or as gifts, largely on phones. The main storefront serves India (jjbags.in, INR, English); a sister storefront serves Saudi Arabia (jj-bags.com, Arabic and English, right-to-left) from the same code.

## Product Purpose

An online shop for JJ Bags: jute bags, cotton canvas bags, printed tote bags, non-woven bags and corporate gifting bags. Success is a shopper finding a bag they like quickly and buying it without friction on a phone.

## Positioning

Undecided. No verified differentiating claim has been confirmed yet.

## Operating Context

The owner manages the catalogue, category names, banners, home-page sections and footer links in the admin panel, and will rename categories; navigation and layouts must hold up for short and long names. Shoppers browse category grids and product pages, then check out with an Indian address (PIN code, GSTIN for business invoices).

## Capabilities and Constraints

- Bagisto-based storefront (Laravel, Blade, Vue 3, Tailwind); server-rendered with admin-managed content.
- Design changes must be front-end only: business logic, prices, cart and checkout behaviour stay as they are.
- One codebase serves both storefronts, so layouts must work in right-to-left Arabic as well.

## Brand Commitments

- The JJ BAGS logo (circular mark) is the only fixed asset. Colours, type, header and footer styling are open.

## Evidence on Hand

- Real product photography: bags cut out on white, in `storage/app/public/product/`.
- Category pictures: transparent cut-outs of product photos, so each store look's tile colour shows behind them.
- Absent, and not to be claimed: years in business, certifications (e.g. Jute Mark India), own manufacturing, custom printing, testimonials, ratings.
- Hero banners and category pictures are JJ Bags' own, composed from the product photos (English set for jjbags.in, Arabic set for jj-bags.com without Hindu religious prints or faith slogans).

## Product Principles

1. The bags are the hero; chrome stays out of their way.
2. Say only what is verified.
3. Phone first: one-handed browsing and buying for Indian shoppers.
4. Owner-managed content must look right whatever the owner types.
