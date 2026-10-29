# KALKI Reference Spec (extracted evidence → Waplia redesign mapping)

Source of truth for the storefront redesign. Everything below was extracted from the
**raw HTML** of https://in.kalkifashion.com/ (Shopify Dawn 13.0.0) saved on
`2026-10-09`. Values are labeled **MEASURED** (read directly from the HTML: hex colors,
fonts, image dimensions/ratios, section order, link URLs) or **ESTIMATED** (inferred —
e.g. live pixel sizes that the HTML cannot fully encode).

Note on the live visual loop: the final "screenshot comparison" step was executed with a
**CDP-driven headless Chrome** (see §10) against both the live reference and the Waplia
storefront at 1440×900 / 1280×800 / 768×1024 / 390×844. Live DOM section order was
verified programmatically at every viewport; pixel content was verified by sampling.
This session's model cannot *view* images, so final pixel-level aesthetic judgment of the
side-by-side screenshots remains for human review (artifacts listed in §10).

No KALKI brand assets, copy, or code are copied. We adapt **structure, order, and
proportions** only, using real Waplia catalog data.

---

## 1. Homepage section order (MEASURED — order of `shopify-section` IDs in the DOM)

| # | Section id | Visual role |
|---|------------|-------------|
| 1 | `kalki_landing_experience_HkJrJE` | Full-screen "How Would You Like To Experience KALKI Today?" modal, `data-show-once` (localStorage), 4 cards: Festive Collection → Explore Now, Bridal Collection → Explore Now, Book a Bridal Stylist → Book Appointment, Visit a Store → Find Nearest Store |
| 2 | `index_round_column_list_new_HTHR6w` | **Nav strip**: text labels `Women | Men | Bridal | luxe` (luxe copper `#aa6437`) + a row of round avatar tiles (60×60): Ready To Ship, Festive/Diwali, Saree, Salwar Kameez, Lehenga, Indo Western, Menswear, Jewellery |
| 3 | `index_top_banner_slider_new` | Hero slider — desktop 1440×540, mobile 764×1080 portrait; slide → collection links |
| 4 | `recently_viewed_banner_wJdnAz` | Thin personalization strip: "Based on your interest … View Your Edit" |
| 5 | `index_icon_with_text_list_new_BVGD4G` | Service strip, icons 50×50: 24-hour Dispatch, Easy Returns, Free Shipping (+ "LEARN MORE ABOUT SHIPPING") |
| 6 | `index_custom_collections_new_Q6pkgL` | 4 mini-banners, 400×533 desktop / 250×353 mobile, title + white `SHOP NOW` button |
| 7 | `collection_video_grid_Lqtdtp` | "Trending Styles" — card grid (product image w-350 + name + `View`) |
| 8 | `tagalys_section_4dfQVU` | Product carousel #1 (JS-rendered widget) |
| 9 | `index_custom_category_new_GgyAbi` | "Top Collection" — category grid |
| 10 | `index_form_app_detail_new_Txf3m7` | "Shop With A Stylist" full-width band, 1438×388: "Expert advice, just a video call away." + "Our Stylists speak English, Hindi, Gujarati & Marathi" + `START CALL NOW` |
| 11 | `tagalys_section_4pD64x` | Product carousel #2 |
| 12 | `index_side_by_side_banner_new_LKaU6a` | "Editor's Picks" — 2-up banners, 800×586 desktop (01-bouse-desk…, 02-dress-material-desk…); mobile 01-bouse-mob… |
| 13 | `index_arrivals_details_new_pkzRNd` | "The Saree Edit" arrivals band |
| 14 | `index_collection_lists_new_PzNwEn` | "Curated Collections" — collection grid (img w-750) |
| 15 | `index_middle_banner_infos_new_fqA8qP` | "Bride And Groom Collection" — `.Common_title_new` centred h2 (24px/500/118.75%) above one full-width 1438×540 (2.66:1) banner image → /collections/bride-and-groom-collection; band margin 18px (12px below 750px, 10px 750–989px). Re-verified live 2026-10-15: the earlier "swiper tiles" note no longer applies — the section is a single linked image |
| 16 | `index_icon_text_info_list_new_dh9jjK` | 2 icon tiles (icons 50×50): "Shop via video call / Get a free virtual styling session / Book an appointment", "The bridal stylist" |
| 17 | `apps_gV7wH3` | "VISIT OUR INSTAGRAM DIARIES / Follow To Know More / @kalkifashion" — instafeed photo grid |
| 18 | `visit_our_store_zcMiCP` | "Visit our Stores" — store cards 345×365, each with `Visit Store` + `View Collection` (KALKI Men Surat, KALKI Vadodara, …) |
| 19 | `index_object_testimonials_new_QFJF7b` | "Customer Stories" — testimonial + name + city + `VIEW PRODUCT` |
| 20 | `home_page_footer_details_new_7WCH3w` | SEO block: `<h1>` + long brand/store description + `Collection_descriptions_infos` read-more |

## 2. Header (MEASURED)

- **Announcement bar**: `background:#000000; color:#ffffff`, swiper of messages with 16px icons:
  clients-icon → "Styled more than 100,000 Clients", video-icon → "Seamless Video Shopping Experience",
  cloth-icon → "Designer Quality styles", truck-icon → "Free Shipping within India"; sticky offset `top:32px`.
- **Main header**: logo (180×55, kalki-logo.svg), search with rotating placeholder
  "Search for style, collections & more...", header icons (account/wishlist/cart).
- **Top utility row** (bottom of header-group, before `<main>`): `Sale` (bag svg),
  `Ready To Ship` (truck svg), `Virtual Appointment` (video svg), `Login` (user svg);
  icon strokes `#223330`.
- **Desktop nav** (from round-list desktop branch): `Women | Men | Bridal | luxe`
  (luxe `color:#aa6437`) followed by round category tiles (see #2 above). Drawer promo card seen: "Hera Couture '21".

## 3. Footer (MEASURED)

- Wrapper `Footer_main_new`, `background: #f7f7f7`.
- Link columns: **Designer Wear** (Salwar Kameez, Sarees, Lehenga, Gowns, Kidswear, Saree Blouse, Kurtis,
  Indowestern Styles, Mens, Accessories, KALKI Influencers, Celebrity Wear, Bridal Wedding Dresses, Zayra-LFW, Inara-LFW II),
  **About Us**, **Policies**, **My Account** (Login, Shopping Bag, Wishlist, Order History, Order Tracking, Buy in bulk).
- Blocks: Safe & Secure Payment, Experience KALKI App On Mobile, Follow Us (social), Get In Touch
  (phone `+91 (22) 4890 0416 (INDIA)`), Email Us On (`info@kalkifashion.com`).
- Link groups: **Latest Ethnic Collection**, **New Arrival**, **Best Seller**, **Quick Links**; a `Measurement` LAUNCH link (icon stroke `#747380`).
- Newsletter block present in footer region.

## 4. Typography (MEASURED)

- **Display / headings**: `Noto Serif Display` (Google Fonts). Highlight accents in copper `#c17f4c` / `#aa6437`.
- **Body**: `Futura` via local `futura-book.woff2` / `futura-medium.woff2` — **not freely
  sublicensable**, so we do NOT bundle it. Substituted with **Jost** (metric-compatible
  geometric sans) on the storefront shell only; documented as a substitution, admin stays on Inter.
- Fallback stack kept (ui-sans-serif / Georgia serif).

## 5. Palette (MEASURED where exact, ESTIMATED where inferred)

| Token | Source value | Status |
|-------|--------------|--------|
| Announcement bg | `#000000` | MEASURED |
| Header icon stroke | `#223330` | MEASURED |
| Nav "luxe" accent | `#aa6437` | MEASURED |
| Landing highlight | `#c17f4c` | MEASURED |
| Footer bg | `#f7f7f7` | MEASURED |
| Landing card buttons | `button_white` (white pill) | MEASURED |
| Section body bg (round list) | `#ffffff` | MEASURED |
| Measurement icon stroke | `#747380` | MEASURED |
| Waplia "ink" (`oklch(0.235 0.018 52)`) | ≈ #47362b | ESTIMATED match for `#223330`/`#000` family |
| Waplia gold-deep (`oklch(0.545 0.095 72)`) | ≈ #9a7a2c | ESTIMATED match for `#aa6437` copper family |
| Waplia ivory/cream | ≈ #fdfcf8 / #f7f4ea | ESTIMATED match for `#ffffff`/`#f7f7f7` surfaces |
| Footer cream | `bg-cream` | ESTIMATED match for `#f7f7f7` |

## 6. Key ratios (MEASURED from image filenames / srcset widths)

| Asset | Ratio |
|-------|-------|
| Hero desktop | 1440×540 ≈ 2.67:1 |
| Hero mobile | 764×1080 ≈ 0.71:1 (portrait) |
| Mini-banners | 400×533 (desk) / 250×353 (mob) ≈ 0.75:1 |
| Side-by-side (Editor's Picks) | 800×586 ≈ 1.37:1 |
| Shop-With-A-Stylist / banner bands | 1438×388 ≈ 3.70:1 |
| Store cards | 345×365 ≈ 0.95:1 |
| Round tiles | 60×60 (source 182×182 / 250×250) |
| Trending card img | w-350 |

## 7. Landing modal behavior (MEASURED)

- Shown once per browser (localStorage key `kl_landing_seen_…`), `display:none` until revealed;
  full-bleed background image `Bg_New_landing.jpg` (~w-1800).
- Heading `How Would You Like To Experience KALKI Today?` (highlight `#c17f4c`),
  divider, sub `Choose the shopping journey that suits you..`.
- 4 cards (img w-500): Festive Collection → / ; Bridal Collection → / ; Book a Bridal Stylist → / ;
  Visit a Store → / pages. Buttons: Explore Now / Explore Now / Book Appointment / Find Nearest Store.

## 8. Mapping to the Waplia storefront (real data only)

| KALKI slot | Waplia component | Source |
|-----------|------------------|--------|
| 2 round list + nav labels | `DepartmentNavStrip` | `useCategories` (real top-level categories) |
| 3 hero 2.67:1 / 0.714:1 | `HeroCarousel` | `HERO` banners |
| 4 personalization strip | `PersonalizationStrip` | real link → `/featured-products` |
| 5 service strip | `ValuePropsSection` | `VALUE_PROPS` items |
| 6 mini-banners 400×533 | `MiniBannerGrid` | `CATEGORIES` (top 4, real images) |
| 7 Trending Styles | `TrendingStyles` | `TRENDING_STYLES` (admin-managed 9:16 cards — image and/or muted looping video, name + `View`) |
| 8 carousel #1 | omitted | `TRENDING_PRODUCTS` section type removed by request (documented) |
| 9 Top Collection | `SubcategoryGrid` | `CATEGORIES` — one signature sub-category per department (10 tiles), 2-up → 3-up → 5-up so desktop = two clean rows |
| 10 Shop With A Stylist band | `TextBanner` | `TEXT_BANNER` (admin-managed background image + title/subtitle/description + one button; dark house gradient when no image is set) |
| 11 carousel #2 | omitted | `TRENDING_PRODUCTS` section type removed by request (documented) |
| 12 Editor's Picks 2-up 800×586 | `EditorsPicks` | real category tiles (1.37:1) |
| 13 The Saree Edit | omitted | `DEALS` section type removed by request (documented) |
| 14 Curated Collections | omitted | `BRANDS` section type removed by request (documented) |
| 15 Bride & Groom 1438px band | `ImageBanner` | `IMAGE_BANNER` (admin-managed full-width banner image; whole band is one link; hidden until an image is set) |
| 16 icon info list | `IconInfoBand` | real routes `/contact`, `/help` |
| + (admin CTA) | omitted | `CTA` section type removed by request (documented) |
| 17 Instagram diaries | `InstagramDiaries` | `INSTAGRAM_DIARIES` (admin-managed photo/video tiles + lightbox) |
| 18 Visit our Stores | `VisitBand` + `StoreLocations` | contact/settings band (`VISIT_STORES`) and admin-managed store-card grid (`STORE_LOCATIONS`: uploaded image, store name, up to two link buttons) |
| 19 Customer Stories | `TestimonialsSection` | `TESTIMONIALS` |
| 20 SEO block | omitted | `SEO_DETAILS` section type removed by request (documented) |
| 1 landing modal | omitted | no gateway data in Waplia (documented) |

## 9. Deliberate deviations / gaps (documented, not hidden)

1. Landing "choose your journey" modal — omitted (no equivalent data; would require fabrication).
2. Trending Styles is admin-managed (`TRENDING_STYLES`) — each 9:16 card takes an optional
   uploaded image and/or a muted, looping video, plus an optional name and link; cards with
   neither media are dropped, and the section is hidden when empty. (KALKI's own cards use
   catalogue video that Waplia has no source for, so media is admin-supplied; demo assets
   ship under `backend/public/demo/instagram/`.)
3. Instagram wall — implemented as an admin-managed `INSTAGRAM_DIARIES` grid (real uploaded images/videos; no live Instagram integration, no fabricated @handle or metrics — each field is admin-entered and optional).
4. Store cards — KALKI's store grid is reproduced as the admin-managed `STORE_LOCATIONS`
   section: every card is an uploaded image + store name + up to two link buttons (text and URL
   both admin-entered), cards without an image are dropped and the section hides when empty.
   Nothing is fabricated — Waplia has no physical-stores table, so the demo seeder builds the
   cards from its real approved vendors (store name + logo, button to `/vendors/:id`). The
   original contact/settings band (`VISIT_STORES`) is kept as a separate section type.
   Card geometry mirrors KALKI's own `visit-our-store.css`: a `345/365` cover crop whose height
   follows the image aspect on desktop and falls back to a 300px min-height (250px on phones) on
   narrow screens, a flat 50%-black caption flush with the image's bottom edge, the store name
   centred in the display serif (14px → 18px → 20px across KALKI's breakpoints), and stacked
   white buttons (`min-width: 130px`, 2px radius, 10px padding, uppercase 12px). KALKI's 10px
   tile padding is reproduced as the 20px cell gutters, so the visible images line up with the
   reference. Layout matches KALKI too: the first four cards sit in a fixed row and any further
   cards go into a 4-per-view snap slider (two per view on phones) with edge arrows.
5. KALKI's brand copy/claims (100,000 clients etc.) are **not** reused; trust messages use
   Waplia's own real capabilities wording.
6. Best Sellers uses the `BEST_SELLERS` API product section type (real data). The
   `TRENDING_PRODUCTS` and `DEALS` section types were removed at the user's request
   (homepage and category-page config); the standalone `/deals` storefront page and its API
   are unaffected.
7. Body font Futura → Jost substitution (licensing) — documented, metric-compatible choice.
8. Image Banner band (KALKI's "Bride And Groom Collection") — KALKI links only the image; here
   the whole band (heading + banner) is a single link, because the heading is admin-supplied.
   KALKI's inline white section background is dropped so the band inherits Waplia's ivory page,
   and the image renders `display:block` (KALKI leaves it inline, which adds ~9px of baseline
   gap). The section type is generic (`IMAGE_BANNER`) rather than bridal-specific, so it can be
   reused for any full-width linked banner; demo art ships under `backend/public/demo/bride-groom/`.
9. Text Banner band (KALKI's "Shop With A Stylist") — KALKI packs two headline lines into one
   `<h2>` (`Shop With A Stylist` + a blank line + "Expert advice, just a video call away.") and
   has no separate description; here the section Title renders as `<h2>`, the Subtitle as `<h3>`
   (same display typography as the title, matching KALKI's second line) and the Description as the
   `<p>` below them. The white CTA is a link, not KALKI's appointment `Popin`. The background image
   is optional; without one the band falls back to the dark house gradient so the white type stays
   legible. Demo art ships under `backend/public/demo/text-banner/` and demo media for the whole
   set was captured for reference only — several screenshots still await human review.
10. The KALKI "SEO block" (slot 20) is omitted — the `SEO_DETAILS` section type was removed at
    the user's request from both the homepage and category-page section config. Product and
    category SEO meta fields (admin product/category forms) are a separate feature and remain.
11. KALKI's admin CTA banner (the unnumbered "+" row) is omitted — the `CTA` section type was
    removed at the user's request from both the homepage and category-page section config.

## 10. Step 9 execution — screenshot & structure verification (headless Chrome via CDP)

Method: a dependency-free CDP driver (Node `WebSocket`) controls `/Applications/Google
Chrome.app` in headless mode — navigate, poll until the storefront renders real content
(≥6 `<img>` in `<main>`), then `Emulation.setDeviceMetricsOverride` + `Page.captureScreenshot`
at each required viewport. Both the live reference and the Waplia storefront were captured
fresh (no cache/localStorage).

Artifacts in `docs/screenshots/`:

| File | Purpose |
|------|---------|
| `waplia-{1440,1280,768,390}.png` | Waplia storefront, exact viewports above |
| `kalki-{1440,1280,768,390}.png` | Live KALKI reference, same viewports |
| `verify-waplia.json` / `verify-kalki.json` | Per-viewport DOM probe: ready flag, `<img>`/link counts, first 400 chars of `<main>` innerText, title |

Measured results:

| Viewport | Waplia `<img>`/links | KALKI `<img>`/links | Waplia PNG size | KALKI PNG size |
|----------|----------------------|---------------------|-----------------|----------------|
| 1440×900  | 119 / 117 | 130 / 157 | 695 kB | 1123 kB |
| 1280×800  | 119 / 117 | 130 / 157 | 535 kB | 919 kB |
| 768×1024  | 119 / 117 | 118 / 157 | 380 kB | 606 kB |
| 390×844*  | 119 / 117 | 129 / 176 | 1873 kB | 1032 kB |

\* 390×844 captured with `deviceScaleFactor: 3` (mobile emulation), hence Waplia's larger PNG.

Live-DOM section-order check (both sites, from `verify-*.json` innerText, top of `<main>`):

- **KALKI (matches §1 exactly):** `WOMEN MEN BRIDAL LUXE` nav labels → round tiles
  `Ready To Ship Diwali Saree Salwar Kameez Lehenga Indo Western Menswear Jewellery …` →
  service strip `24-hour Dispatch Easy Returns…` → four mini-banners each ending `SHOP NOW`
  → `Trending Styles … View` → (carousels/product rails continue below).
- **Waplia (matches §8 mapping):** nav-strip category labels → hero copy
  (`Welcome to Our Marketplace… Shop Now / Explore offers`) → `VIEW YOUR EDIT`
  (personalization) → `Easy returns Secure payments Fast delivery` (service strip) → …
  Each viewport reports `ready: true` with the full 119-image catalogue rendered.

Pixel-content check (sampled BMP analysis, 1440×900): KALKI mean RGB (161,159,150) /
stdev ≈ 97; Waplia mean RGB (138,135,130) / stdev ≈ 106; both ≈ 43–44 % near-white.
Composition is comparable at the top (bright header band followed by dense content);
KALKI's first viewport is more photo-dense (fashion editorial) than the generic
marketplace catalogue — an expected content difference, not a layout deviation.

Limits: (1) this model cannot view images, so aesthetic pixel-diffing beyond the above
metrics is left to human review of the PNGs; (2) the KALKI "choose your journey" landing
modal did not appear in the fresh-profile captures (its HTML evidence in §1/§7 stands;
the modal may be entry/geo/experiment gated).

## 11. Product page (PDP) — KALKI composition → Waplia `/product/:id`

Reference: `https://in.kalkifashion.com/products/baby-pink-tissue-saree-with-velvet-embroidered-blouse`
Measured at 1440×900 via the same CDP driver (see §10 method). Waplia target:
`frontend/src/features/website/pages/ProductPage.tsx`.

### 11.1 Composition (MEASURED on KALKI, replicated)

Full-page order: breadcrumb (`Home / Saree / Embroidered Saree`) → promo strip (global,
omitted — see §11.4) → two-column grid → full-width rails → Customer Reviews.

| # | Element | KALKI (measured) | Waplia (measured on `/product/140`) |
|---|---------|------------------|--------------------------------------|
| Grid | gallery \| gutter \| buy box | 838px \| 70px \| 412px | 838px (88 thumbs + 30 gap + 720 main) \| 70px \| 412px ✔ |
| Thumbs | vertical strip, per thumb | 88×123 | 88×123 (desktop), 66×92 (mobile) ✔ |
| Main image | portrait | 720×1012 | 720×1008 (aspect 5/7; 4px shorter — within tolerance) ✔ |
| Sticky | gallery sticks while buy box scrolls | sticky | `lg:sticky top-28` (computed `sticky`, top 112px) ✔ |
| Title `h1` | 18px / 500 / capitalize / ink `#0F0D1A` | same | 18px / 500 / capitalize / ink token ✔ |
| Style No | 14px / 400 ink | same | `Style No: <sku>` 14px/400 ✔ |
| Price | sale 18px / 400 / ls 1.3px ink | same | 18px / 400 / ls 1.3px ✔ |
| MRP | 16px grey `#73717E` strikethrough + "MRP" label | same | 16px `#73717E` line-through ✔ |
| % off | copper `#AA6437` | same | 13px/500 `#AA6437` ✔ |
| Taxes | 12px `#756F6F` "Inclusive of all taxes" | same | 12px `#756F6F` ✔ |
| Option row | label 18px/400 "… : value" over `border-top rgba(73,71,86,.15)` | same | 18px label + value 500 over `border-[#494756]/20` ✔ |
| Pills | radius 28, 14px, pad 12×16 (h≈46), selected bg `#3F3D4D` white, idle white+1px border | same | radius 28, 14px, h 46, selected `bg-[#3F3D4D]` (verified by click), idle white+border ✔ |
| Colour | circular swatch + ring | (swatch on generic PDPs) | 40px circles, active ring `#172926`, disabled at opacity-30 ✔ |
| Delivery | 16px "Estimated delivery : …" (value 500) | same | 16px with 500 value ✔ |
| Sold-out | note red `#F4473D` on light-blue bg `#E4ECFF`, 3px border `#AAC2FC` | same | same classes ✔ |
| ADD TO CART | 412×47, white, 2px `#172926` border, ink text incl. price, uppercase 14px/500, ls .56, radius 6 | same | 412×47, verified bg/border/ls/radius 6 ✔ |
| Buy it now | 412×47, solid `#172926`, white, radius 6 | same | 412×47 solid `#172926` ✔ |
| Trust | 2×2, 16px links | same slot | 2×2 16px: Purchase Protection / N-day returns / Assured Quality / Secure payments ✔ |
| Accordions | 20px/500 capitalize headers, 60px tall on `border-top` (in the buy column) | same | Product Details / Style & Fit Tips / Cancellation, Shipping & Returns (20px headers, collapsible) ✔ |
| Rails | 24px/500 titles, 255-wide cards | similar | "Similar Products" / "Complete Your Look" / "Recently Viewed" / "Customer Reviews" 24px ✔ |

Verified with a click during capture: selecting size `4-5Y` switches the pill to
`bg rgb(63,61,77)` = `#3F3D4D` / white — the exact KALKI active state.

### 11.2 Functional behaviour preserved (all real)

Variant gating (combination availability), quantity stepper, ADD TO CART (auth → token
→ cart) with price sum, Buy it now → checkout, wishlist (auth-gated), coupon-copy chips
from `/offers`, Reviews + Questions & Answers, Compare, back links, sticky mobile buy bar
(`display:none` ≥1024, `block` on 390/768 — verified), breadcrumb via real category /
subcategory slugs, `recordProductView`, related / FBT / recently-viewed rails (real API
data; rails auto-hide when a product has no related entries).

### 11.3 Branch coverage verified (CDP, 1440)

| Page | Result |
|------|--------|
| `/product/140` (16 variants: size+colour) | h1 18px, both CTAs, size pills + colour swatches, clickable gating ✔ |
| `/product/147` (0 variants) | h1 + CTAs, no option groups rendered ✔ |
| `/product/142` (2 variants) | h1 + CTAs ✔ |
| `/product/1` (does not exist — API 404) | not-found state (no h1, "Back to Shop") ✔ |

### 11.4 Deviations (documented, not hidden)

- **Promo/offer strip** (KALKI 1400×92 full-width campaign image above the grid): no
  per-product campaign-banner data source → omitted; the real "Available offers" coupon
  block is kept in the buy column instead.
- **"Check Location" pin-code checker** (KALKI dark `#172926` button + input): there is no
  pin-code/shipping-estimate endpoint on the backend → replaced with the real shipping
  text "Estimated delivery : Usually ships within 2–4 business days" + N-day returns line
  (same slot, honest label).
- **Size-guide / "Learn More" copper links**: no size-chart data exists → links omitted
  rather than dead-ending.
- **KALKI FAQ block** (buy-column accordion): Waplia keeps the live Q&A engine as a
  full-width "Questions & answers" section with composer + list (real data), plus the
  three buy-column accordions. No fabricated FAQ text.
- **Social-proof counter** ("39 people are viewing…"): no such metric in the backend →
  omitted.
- **Product colours seed from admin color catalogue** (`GET /colors`) with a local hex
  fallback map; KALKI's swatch hues are product-specific images and are not replicable.
- **Rails hide when empty** — the support rails only render when the API returns data
  (`related` / `frequently-bought` / `recently-viewed`). `related` and `frequently-bought`
  are real, order-driven endpoints: `frequently-bought` counts co-purchased products across
  delivered/completed orders, so it is only as rich as the demo order history. The
  `CoPurchaseDemoSeeder` seeds 544 delivered, multi-item orders (one anchored on every
  product, companion pieces drawn from the same department), so "Complete Your Look" now
  renders on every PDP with 2–8 cards. `Recently Viewed` requires the customer token.
- **"Recently Viewed"** requires the customer token (`/user/recently-viewed`); logged-out
  viewers see no rail (honest — KALKI's rail is personalised too).
- **Radius token**: shadcn `rounded-md` resolves to 10px in this theme; the CTAs force
  `rounded-[6px]` to match KALKI exactly.

### 11.6 User-requested overrides (post-KALKI-match phase)

- **Gallery shows the image at its natural aspect, no crop/zoom** (user: "product image is
  zoomed i want proper image size as the user submitted or uploaded"). The main frame is
  now `bg-white` `h-[420px] sm:h-[min(68vh,840px)]` with `object-contain` — the full
  uploaded photo is always visible, letterboxed on white for non-square uploads. KALKI's
  5:7 `object-cover` crop + hover-zoom + "Zoom" hint are removed (deviation from the
  reference, per explicit user request). Verified live on `/product/19`:
  `natural 900x900`, rendered rect 720×612 (1440) / 358×420 (390), `objectFit: contain`.
- **Support rails are sliders with redesigned cards** (user: "Similar Products cards ui
  more attractive and put them in a slider"). New `components/common/ProductRail.tsx`:
  snap-scroll track, edge arrow buttons (auto-hide at ends, scroll-by-card), and a new
  `RailProductCard` — rounded-2xl ivory card, 4:5 image with hover crossfade/scale, %OFF
  chip (`#F2D6C2`/`#3A0B08`), Bestseller/Featured/Sold-out badges, functional wishlist
  heart, rating chip, price/MRP/% row, hover "View options / View product" pill. Applies
  to Similar Products, Complete Your Look and Recently Viewed. Verified: 7 cards on
  `/product/19` (track `scrollWidth 1832 > client 1336`), arrow click moves `scrollLeft
  0 → 264`.
- **Redesigned Reviews / Write-a-review / Q&A** (user: "Customer Reviews and Write a
  review and Questions & answers ui also attractive"). `ReviewSection` now renders a
  rating-summary card (big average, stars, count, 5→1 distribution bars — computed from
  live data) when reviews exist, avatar-initial review cards with Verified Purchase chip
  and image thumbs, a polished inline "Write a review" form (star picker, title, body,
  image URLs, delete-preview), and a guest "Sign in to write a review" prompt.
  `ProductQuestionSection` renders an "Ask a question" composer (when signed in),
  avatar-initial Q cards, and seller answers with a gold accent rule + "Seller answer"
  badge. Internal duplicate `id="reviews"` + duplicate h2 removed (ProductPage owns the
  section headings). Verified: `/product/19` avg 4.5 + 2 review cards; `/product/6` avg
  3.8 + 4 review cards.

### 11.5 Artifacts (this task)

`docs/screenshots/waplia-pdp-{1440,1280,768,390}.png` (new PDP capture, real data),
`kalki-pdp-{1440,1280,768,390}.png` (reference PDP at the same viewports),
`verify-waplia-pdp-*.json` (per-viewport measured probe incl. pill-click check),
`verify-kalki-pdp.json` (reference image/link counts).
`docs/screenshots/waplia-pdp-19-{1440,390}.png` + `waplia-pdp-6-1440.png` (refresh-phase
captures: natural-size gallery, rail slider, redesigned reviews/Q&A).

## 12. Category / collections page — match to KALKI `collections/lehenga`

Reference page measured via CDP (1440): http://in.kalkifashion.com/collections/lehenga.
See `docs/screenshots/kalki-lehenga-{1440,390}.png`.

### 12.1 Measured KALKI collection page (what we matched)

Measured order on the reference (CDP, 1440): breadcrumb (`Home / Women / Lehenga`, y≈187)
→ **full-bleed banner image** (`highlighted-banners-desktop-image`, y≈348, h≈385) → **H1
title below the banner** (`Shop Latest Collection of Lehengas…`, y≈781) → vertical facet
sidebar (`FILTERS` + `Price` / `Product Category` / `Sub Categories` accordions) next to
the grid, with `Sort By` select → **product grid** → numbered pagination. "Select from Our
Popular Lehenga Styles" tiles sit *after* the grid; SEO/editorial blocks follow.

- `Sort By` select — options `Trending / Best selling / New / Price: high to low / Price:
  low to high`; product count; numbered pagination.
- Product grid: **4 columns × 270px cards** (content ~1140px, 20px gutters), card
  `grid__item` 270×493. Product image aspect ≈ **0.71 (5:7 portrait)** with occasional
  square shots.
- Card anatomy: image full-bleed → title → `Sale price ₹X` + struck `MRP ₹Y` + accent
  `X% OFF` → microcopy (`Ready To Ship`); badges (`New This Week`, sale chip) on image.

### 12.2 Waplia implementation (`features/website/pages/CategoryPage.tsx` + new
`components/common/CollectionCard.tsx`) — all real data, no fabrications

- **Removed merchandising rails** per user request: `FEATURED_PRODUCTS` ("Featured in
  Staples"), `DEALS` ("Hot Deals") and `BEST_SELLERS` ("Best Sellers in Staples") no
  longer render on any category page (the old `CategoryProductRail` is deleted).
- **Removed the "Top brands" (`BRANDS`) section type entirely** per user request — the
  `CategoryBrandStrip` (category pages) and `BrandStrip` (homepage) components are deleted,
  along with the section-type option in the homepage and category-page admin editors. Brand
  data still drives the filter sidebar and the standalone `/brands` pages.
- **Page order = KALKI**: Breadcrumb (now the full category path, real tree — e.g.
  `Home / Women's Clothing / Dresses`) → `CategoryBanner` (banner image inset in the
  `container-store` gutter, no overlaid title) → `CategoryProductListing` — a **two-column
  layout** (see §12.4): filter sidebar on the left and, level with it, the H1 title block +
  toolbar + grid + pagination on the right, exactly like KALKI where the H1 sits *beside*
  the facets, not in a separate full-width hero → `CategoryTiles` ("Shop by category",
  maps to "…Popular Styles", after the grid) → `CategoryTestimonials`.
- **Toolbar**: `{{count}} products` + `Sort By` select (KALKI-style labels: New, Best
  selling, Price: low/high, Top rated, Biggest discount — values/sorting unchanged);
  mobile `Filters` dialog kept functional; sticky desktop filter sidebar restyled.
- **Grid cards** (`CollectionCard`): 5:7 portrait image with hover crossfade +
  quick-add bar (real add-to-cart; variant products route to their PDP; wishlist heart
  auth-gated; Sold-out / Only-X-left chips), then brand label / title / rating chip or
  "New arrival" / price row (`₹ price` + struck MRP + `-X%` in claret).
- Kept (real data): Breadcrumb + "Loved by our community" testimonials. KALKI's rich
  editorial/FAQ/blog blocks have no backend source → not fabricated.

### 12.3 Verified (CDP, live storefront `/category/staples`)

- Sections removed: "Best Sellers" / "Hot Deals" / "Featured in" / "Top brands"
  ("Shop labels") → all absent from the DOM.
- Order verified (1440): `H1:Staples` (y≈719) → grid (y≈972, 13 cards) →
  `Shop by category` tiles (y≈3010) → `Loved by our community`.
- Banner: full-bleed `600×450` real image, rendered 1440×400.
- 13 products rendered as 4-col portrait cards (see §12.4 for the refreshed widths);
  product count `13 products`; sort default `New`; no pagination (single page —
  PaginationBar hides at lastPage ≤ 1 as designed).
- Quick-add hover: mouse over a card reveals `Add to bag` bar (transform `none` on
  hover, no navigation), e.g. card "23.3% OFF · BESTSELLER · GOLDEN HARVEST Whole
  Wheat Atta 5kg · ₹329.00 ₹429.00 -23.3%".
- Screenshots: `docs/screenshots/waplia-category-staples-{1440,390}.png`;
  reference `docs/screenshots/kalki-lehenga-{1440,390}.png`.

### 12.4 Refinement phase — full KALKI layout parity (`/category/dresses`)

Second pass, driven by "same type of UI as `in.kalkifashion.com/collections/lehenga`" for
`/category/dresses`. Measured the reference again (CDP 1440): content **1400px** wide on a
16px gutter, filter sidebar **201px**, grid **4 × 270px** (20px gutters), card image
270×384 (**ratio 0.704**), H1 24px sitting at the **same y as the FILTERS label** (so the
H1 lives in the grid column, beside the facets), pill `Sort By` select 237×37 with a 1px
dark border, breadcrumb 3 levels.

Changes applied (all real data, functionality intact):

- **Container** — every category section now uses the storefront shell's `container-store`
  (max-w **1440**, 16/24/32 gutters) instead of `max-w-7xl`, so the page aligns with the
  header/footer *and* reaches KALKI's width.
- **Two-column listing** — `lg:grid-cols-[200px_minmax(0,1fr)]`; the `H1` (new
  `CategoryTitleBlock`, was `CategoryIntro`) moved *inside* the right column, so it aligns
  with the top of the filter sidebar, exactly like KALKI. New `CategoryPageLoading` mirrors
  the same two-column skeleton.
- **Banner** — no longer full-bleed; inset within `container-store` (KALKI's banner is
  inset in the page gutter, not edge-to-edge).
- **Filter sidebar** — restyled to KALKI's compact facet list: plain (no card/shadow),
  uppercase `FILTERS` heading with an active count, `Clear all`, and **collapsed-by-default
  accordion rows** with a bottom hairline (was a white card with all groups open).
- **Breadcrumb** — builds the real ancestor chain via `useCategories()` (dresses →
  `Home / Women's Clothing / Dresses`; top-level categories → `Home / <name>`); intermediate
  levels link to their `/category/:slug`.
- **Toolbar / sort** — dark-bordered pill select kept (KALKI 20px radius + dark 1px border).
- H1 reduced to 2xl→32px (KALKI title is modest, 24px) to match the reference's hierarchy.

Verified live (CDP, `/category/dresses`, 1440 static + 390 mobile):
breadcrumb `Home Women's Clothing Dresses`; container x=0 w=1440; sidebar **x=32 w=200**;
H1 x=272 (KALKI 276) at the sidebar's top y; grid **4 × 269px** at x=272/561/850/1139
(KALKI 270 at 276/566/856/1146); card image **269×377 (0.713, KALKI 0.704)**; sort pill
x=1184 w=224 (KALKI x=1179 w=237); facets `Brand / Price / Discount / Customer rating /
Availability` collapsed ~53px each (KALKI facets 50px); count `5 products`; mobile 2-col ×171px.
Screenshots: `docs/screenshots/waplia-dresses-{1440,390}.png`; reference full-page
`docs/screenshots/kalki-lehenga-full-1440.png`.
### 12.5 Filter sidebar facets — Product Category / Sub Categories / Color / Size
KALKI's collection sidebar exposes taxonomy + attribute facets (`Product Category`,
`Sub Categories`, `Size`, `Color`, …). Waplia now mirrors those groups from **real data**
(no mock options) and drops the redundant "Shop by category" tiles section.

- **Backend** (`Api\V1\User\ProductController@facets`, only when `?with_facets=true`):
  added two real, scope-aware facets alongside the existing `brands`/price/`attributes`:
  - `categories` — distinct `category_id` of live in-scope products, with real product
    counts (`categoryFacets()` helper maps ids → `{id,name,slug,count}`).
  - `subcategories` — distinct `subcategory_id`, same shape.
  - The `attributes` facet `count` was corrected from *variant rows* to **distinct
    products** per value (e.g. Color `Rose` = 4, not 20).
- **Frontend** (`CategoryPage.tsx`):
  - Query adds `category_id`, `subcategory_id`, `color`, `size`, `with_facets: true`
    (all already supported by `GET /products`; `ProductQuery` extended accordingly).
  - Sidebar groups (in order): **Product Category → Sub Categories → Color → Size →
    Brand → Price → Discount → Customer rating → Availability**. Category/Brand groups
    come from `facets.categories/subcategories/brands` with live counts; Color renders
    **swatches**, Size renders **pill chips**; every option toggles off on re-click.
  - URL params: `product_category`, `sub_category`, `color`, `size` (changing
    `product_category` clears `sub_category`). Active-filter chips + `Clear all` cover all.
  - Removed the `CategoryTiles` ("Shop by category") component and its BRANDS/CATEGORIES
    section wiring; the sidebar is now the single browse/filter surface.

Verified live (CDP, `/category/dresses`): 9 facet groups render; `Shop by category` absent;
clicking `Rose` → count `4 products`, URL `?color=Rose`, chip `Rose`; sidebar order matches
spec. Screenshots: `docs/screenshots/waplia-dresses-facets-1440.png` (expanded desktop),
`waplia-dresses-1440.png`, `waplia-dresses-filters-390.png` (mobile drawer).
Gates: `php artisan test` 522/522, Pint clean, `npm run lint` exit 0, `npm run build` exit 0.
