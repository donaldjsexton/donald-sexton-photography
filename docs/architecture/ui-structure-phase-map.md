# UI Structure Phase Map

This phase treats the homepage as the source of truth for the site’s page grammar.

## Shared Page Types

- `Home`
  - brand-led hero
  - supporting statement
  - selected pathways into portfolio and journal
  - conversion close

- `Archive`
  - page hero
  - section heading
  - repeatable archive cards or story features
  - conversion close

- `Detail`
  - page hero
  - reading/media section
  - optional related content sections
  - conversion close

- `Conversion`
  - page hero
  - focused form or confirmation state

## Shared Primitives

- `editorial.page-hero`
- `editorial.page-closing`
- `editorial.section-heading`
- `editorial.archive-card`
- `editorial.reading-section`
- `editorial.story-feature`
- `editorial.media-frame`

## Route Mapping

- `/`
  - `Home`

- `/weddings`
  - `Archive`

- `/weddings/{slug}`
  - `Detail`

- `/journal`
  - `Archive`

- `/journal/{slug}`
  - `Detail`

- `/collections`
  - `Archive`

- `/venues`
  - `Archive`

- `/venues/{slug}`
  - `Detail`

- `/about`
  - `Detail`

- `/locations/{slug}`
  - `Detail`

- `/inquire`
  - `Conversion`

- `/thank-you`
  - `Conversion`

## Next Structural Work

- move remaining repeated content wrappers into shared components
- normalize CTA language across all routes
- reduce route-specific markup where shared primitives already exist
- keep styling changes secondary to structural consistency
