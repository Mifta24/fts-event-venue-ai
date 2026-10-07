# FTS APARTMENT AI — Project Concept

## 1. Project Name
**FTS APARTMENT AI**

## 2. Product Vision
FTS APARTMENT AI is a full-screen, one-page interactive apartment experience.

The website should feel like the customer is entering a real apartment lobby and being welcomed by an AI receptionist / concierge.

This is **not** a traditional apartment website with long scrolling sections.

The entire experience runs inside a fixed viewport:

- `height: 100vh`
- `overflow: hidden`
- no long vertical scrolling
- scene-based navigation
- chat, unit information, reservation, facilities, and handover appear as overlays, panels, or scene transitions

## 3. Core Experience

```text
Open Website
    ↓
Opening Lobby
    ↓
[ Enter FTS APARTMENT AI ]
    ↓
AI Receptionist
    ↓
Choose Need / Ask AI
    ↓
Unit / Facilities / Reservation / Apartment Info
    ↓
Human Handover if needed
```

## 4. Main UX Principle
The customer should never feel like they are browsing many pages.

Instead, the website behaves like an interactive application.

Traditional structure to avoid:

```text
Home
↓
About
↓
Units
↓
Facilities
↓
Contact
↓
Footer
```

Target structure:

```text
Lobby
↕
Reception
↕
Unit
↕
Facilities
↕
Reservation
↕
Staff Handover
```

## 5. Scene 0 — Opening Lobby
This is the first screen.

Purpose:
- establish the apartment atmosphere
- introduce the FTS APARTMENT AI brand
- provide one clear action to enter the experience

Main elements:
- full-screen luxury apartment lobby
- FTS APARTMENT AI logo
- short tagline
- one primary CTA

Suggested CTA:

**Enter FTS APARTMENT AI**

Alternative:

**Enter Apartment**

Suggested tagline:

**AI Concierge for a Smarter Stay**

or

**Your Intelligent Apartment Experience**

## 6. Scene 1 — AI Receptionist
After pressing Enter, transition to a closer reception view.

Main elements:
- AI receptionist / concierge
- apartment branding
- AI greeting
- quick action menu
- chat input
- optional microphone
- no page scroll

Suggested quick actions:
- Check Units
- Reservation
- Facilities
- Apartment Information
- Talk to Staff

Example greeting:

> Welcome to FTS APARTMENT AI. How can I assist you today?

## 7. Scene 2 — Unit Experience
When the customer chooses units:

- scene changes without leaving the app
- unit image / unit visual becomes the main focus
- AI remains available
- unit information appears in a panel
- customer can continue chatting

Information:
- unit name
- unit images
- unit size
- bed type
- occupancy
- unit facilities
- layout (studio / bedrooms / bathrooms) and floors
- price range, plus weekly and monthly long-stay rates
- smoking policy
- availability status if connected

Actions:
- Ask AI
- Previous Unit
- Next Unit
- Reservation
- Back to Reception

## 8. Scene 3 — Facilities
Facilities appear as a full-screen scene or overlay.

Possible categories:
- Restaurant
- Swimming Pool
- Gym
- Spa
- Meeting Unit
- Parking
- Wi-Fi
- Airport Transfer

Actions:
- Ask About Facility
- View Another Facility
- Back to Reception

## 9. Scene 4 — Reservation
Reservation should be guided, not a long form.

Steps:
1. Check-in
2. Check-out
3. Guests
4. Number of units
5. Preferred unit
6. Customer name
7. Contact
8. Summary

Final actions:
- Submit Reservation Request
- Send to WhatsApp
- Contact Apartment Staff
- Back

## 10. Scene 5 — Human Handover
If the user needs staff support:

- AI explains that an apartment staff member can assist
- conversation summary is prepared
- user chooses a handover channel

Channels:
- WhatsApp
- Phone
- Email

## 11. AI Concierge Role
The AI acts like a professional apartment receptionist.

Responsibilities:
- welcome visitors
- answer apartment questions
- explain units
- explain facilities
- explain policies
- support reservation requests
- recommend suitable unit types
- hand over to staff when necessary

The AI must never invent apartment facts.

## 12. Language
V1:
- English
- Indonesian

Future:
- Japanese
- Chinese
- Korean

## 13. V1 Scope

### Must Have
- FTS APARTMENT AI branding
- 100vh full-screen layout
- no long vertical scroll
- opening lobby scene
- Enter button
- AI receptionist scene
- unit scene
- facilities scene
- reservation scene
- AI chat
- apartment knowledge base
- WhatsApp / staff handover
- desktop + mobile responsive
- English + Indonesian

### Nice to Have
- voice input
- AI voice response
- animated receptionist
- advanced scene transitions
- chat history panel

### Not Required for V1
- direct payment
- PMS integration
- OTA integration
- full 3D apartment
- real-time avatar lip-sync
- autonomous final booking confirmation

## 14. Success Criteria
The V1 is successful when a visitor can:

1. Open the website and see an apartment lobby.
2. Enter FTS APARTMENT AI from one clear button.
3. Meet the AI receptionist.
4. Ask questions naturally.
5. View unit options.
6. Ask about facilities.
7. Start a reservation.
8. Contact human apartment staff.
9. Complete the whole journey without scrolling through a long webpage.


## Apartment-specific behaviour

What makes this an apartment product rather than a hotel one:

- **Unit types, not rooms.** Each unit type has a layout (`bedrooms`, `0` for a studio), `bathrooms` and a `floor_range`, shown on the unit page, in the directory board and in the concierge's tool results.
- **Long stays.** Requests go up to 90 nights online (`ReservationService::MAX_NIGHTS`). Each apartment sets `weekly_discount_percent` (from 7 nights) and `monthly_discount_percent` (from 28 nights); `ReservationService::quote()` applies it, so the wizard, the concierge tools and the stored booking total always agree.
- **The building as navigation.** Every section is a floor (`ApartmentPageController::FLOORS`): L lobby, 02 residences, 03 shared facilities, 04 building information, 05 stay request, 06 apartment team. The lift panel is the menu, the header shows a floor display, and page changes close and reopen lift doors.
