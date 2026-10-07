# FTS APARTMENT AI — Scene & Asset List

## 1. Scene 0 — Opening Lobby

Required:
- lobby desktop background
- lobby mobile background
- FTS APARTMENT AI logo
- tagline
- Enter button
- optional language selector

Suggested filenames:

```text
lobby-desktop.webp
lobby-mobile.webp
fts-apartment-ai-logo.svg
```

## 2. Scene 1 — AI Reception
Required:
- reception desktop background
- reception mobile background
- AI receptionist / concierge image
- chat UI
- quick action icons

Suggested:

```text
reception-desktop.webp
reception-mobile.webp
ai-receptionist-default.webp
```

Optional AI states:
- greeting
- listening
- speaking
- thinking

## 3. Scene 2 — Units
For every unit:
- hero image
- gallery
- thumbnail
- optional background crop

Example:

```text
units/
  deluxe-king/
    hero.webp
    01.webp
    02.webp
    03.webp
```

## 4. Scene 3 — Facilities
Assets:
- restaurant
- pool
- gym
- spa
- meeting unit
- parking
- airport transfer

## 5. Scene 4 — Reservation
UI assets:
- date icon
- guest icon
- unit icon
- contact icon
- confirmation icon

No separate large background is required if using modal / overlay.

## 6. Scene 5 — Human Handover
Assets:
- WhatsApp icon
- phone icon
- email icon
- optional staff / consultation visual

## 7. AI Receptionist Requirements
The character should:
- look like apartment staff
- remain visually consistent
- use the same uniform
- use similar lighting
- be suitable for desktop and mobile crop

Recommended:
- transparent WebP / PNG when layering
- high-resolution source retained separately

## 8. Brand
Required:
- FTS APARTMENT AI logo
- favicon
- light logo
- dark logo
- approved typography
- brand color reference

## 9. UI Icons
- Enter
- Units
- Reservation
- Facilities
- Info
- Staff
- Microphone
- Send
- Back
- Close
- Language

Prefer SVG.

## 10. Image Size Guidance
Desktop source:
- minimum 1920×1080

Mobile source:
- minimum 1080×1920

## 11. Performance
- use WebP / AVIF where possible
- compress
- lazy-load non-current scenes
- preload lobby and reception
- do not load every unit image at initial startup

## 12. Asset Checklist

### Opening Lobby
- [ ] Desktop lobby
- [ ] Mobile lobby
- [ ] FTS APARTMENT AI logo
- [ ] Tagline
- [ ] Enter button styling

### Reception
- [ ] Desktop reception background
- [ ] Mobile reception background
- [ ] AI receptionist
- [ ] Action icons
- [ ] Chat UI

### Units
- [ ] Unit 1
- [ ] Unit 2
- [ ] Unit 3

### Facilities
- [ ] Restaurant
- [ ] Pool
- [ ] Gym
- [ ] Other facilities

### Responsive
- [ ] Desktop crops
- [ ] Tablet crops
- [ ] Mobile crops
