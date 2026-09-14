# Homepage menu and banner

The current reference is the user-supplied September 14 mockup, saved as public/assets/images/mmc/brand-reference.png. The older mockups/01-homepage.png shows a different design.

The homepage includes partials/mmc-hero.blade.php. Its isolated styles and mobile navigation are in public/assets/css/mmc-home.css and public/assets/js/mmc-menu.js. Other pages and the lower homepage sections retain the original template.

The banner background is a generated recreation of the supplied hero photo with all text removed. The header uses the user-supplied public/logo.png; CSS trims its outer canvas whitespace for the header. The generated logo draft is not used.

Navigation and the banner CTA use existing Laravel routes. Cakes, Pastries and Cannoli carry category query parameters; product filtering remains part of the future backend work.

The hero now contains four banners configured in partials/mmc-hero.blade.php. mmc-slider.js provides horizontal transitions, arrows, indicators, keyboard navigation, touch swipes and six-second autoplay. Rotation pauses on hover/focus and respects reduced motion; the pause button stops it explicitly. The logo is smaller on desktop and mobile. The original template's timed newsletter popup is disabled on this homepage so it does not interrupt the banner.

Do not rerun ../.tools/convert.cjs: it is the initial import script and would overwrite the customized views and assets.

The original testimonial block is the second section, directly after the hero. partials/mmc-tradition.blade.php contains fixed Our Tradition text over one Caprese background. mmc-tradition.css and mmc-tradition.js provide gentle scroll-driven background movement; this section has no rotating images or slider controls. Mobile and reduced-motion layouts keep the background still. The original testimonial faces, ratings and placeholder copy have been removed from the homepage.
