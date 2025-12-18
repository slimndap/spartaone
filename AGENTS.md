# Frontend layout guidelines

- Containers: use `main.container` with the shared `--page-max-width` variable (set in `assets/css/custom.css`) to keep all views aligned. Avoid per-view max-width overrides; prefer adjusting the root var if needed.
- Cards/panels: default to the glass-card pattern (`glass-card card shadow-lg`) for primary blocks. Avoid nesting cards inside cards; prefer padding and grid/stacked layouts inside a single card.
- Headings: use the shared label classes (`section-label`) for small uppercase intros, then a clear title or value beneath. Keep a single visual hierarchy per card.
- Colors: stick to brand tokens defined in `:root` (`--brand-accent`, `--brand-accent-strong`, surfaces/borders) and avoid ad-hoc hex colors. Interactive elements should use brand accent for focus/active states.
- Spacing: favor Bootstrap spacing utilities for consistent gaps; keep vertical rhythm inside cards with small margin-bottoms on labels and values.
- Slider/inputs: when styling inputs (e.g., tempo slider), use brand accent for thumb/track; keep units attached to values so live updates remain readable.
