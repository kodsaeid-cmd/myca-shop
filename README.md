# MYCA Shop — فروشگاه مایکا

## Deployment preview

This repo contains the self-contained static MYCA Shop editorial prototype in `index.html` (based on MYCA Magazine design language). It is prepared for a Vercel **static** project with target custom domain `shop.dandoonii.ir`.

**Demo only — NOT a production ecommerce system:** no actual checkout, payment, database, user authentication, server-side admin, inventory, shipping, or orders. Demo data is stored in the local browser, and admin features are not protected. Do not enter personal or payment information.

All pages are `noindex` through meta tags, HTTP response headers (`vercel.json`) and `robots.txt` until a real production system is ready.

### Connect

1. Import `kodsaeid-cmd/myca-shop` as a project on Vercel with framework `Other` and no build step.
2. Verify the production preview deployment; assign `shop.dandoonii.ir` through project Domains.
3. Create the DNS record exactly as advised by Vercel in the authoritative DNS provider, then verify the custom domain and HTTPS.
4. Do not replace DNS records for magazine or any other deployed service.

`MYCA_DESIGN_LANGUAGE_v1.0` is the visual reference; this is a UI/interaction preview, not a production transactional stack.
