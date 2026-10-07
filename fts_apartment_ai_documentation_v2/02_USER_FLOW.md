# FTS APARTMENT AI — User Flow

## 1. Global User Flow

```text
Open Website
    ↓
Opening Lobby
    ↓
Click "Enter FTS APARTMENT AI"
    ↓
Reception Scene
    ↓
AI Greeting
    ↓
Customer:
    ├── asks a question
    ├── checks units
    ├── checks facilities
    ├── starts reservation
    └── asks for staff
```

## 2. Opening Flow

### Step 1
Customer opens the website.

### Step 2
System shows:
- full-screen apartment lobby
- FTS APARTMENT AI logo
- tagline
- Enter button

### Step 3
Customer clicks:

**Enter FTS APARTMENT AI**

### Step 4
Transition to reception scene.

No scrolling is required.

## 3. Reception Flow

```text
Reception Scene
    ↓
AI says welcome message
    ↓
Quick Actions appear
    ↓
Customer chooses action or types a question
```

Quick Actions:
- Check Units
- Reservation
- Facilities
- Apartment Information
- Talk to Staff

## 4. Unit Flow

```text
Check Units
    ↓
AI asks optional dates / guest count
    ↓
Unit options appear
    ↓
Customer selects unit
    ↓
Unit Scene opens
    ↓
AI explains selected unit
    ↓
Customer can:
    ├── Ask AI
    ├── Next Unit
    ├── Previous Unit
    ├── Start Reservation
    └── Back to Reception
```

## 5. Facilities Flow

```text
Facilities
    ↓
Facility menu / cards appear
    ↓
Customer selects facility
    ↓
Facility Scene / Panel opens
    ↓
AI explains details
    ↓
Back to Reception
```

## 6. Reservation Flow

```text
Reservation
    ↓
Check-in
    ↓
Check-out
    ↓
Guests
    ↓
Number of Units
    ↓
Preferred Unit
    ↓
Customer Name
    ↓
Contact
    ↓
Summary
    ↓
Submit Request / WhatsApp
```

## 7. Human Handover Flow

Trigger:
- user asks for staff
- AI lacks confirmed information
- payment issue
- complaint
- group booking
- special price request
- complex booking

Flow:

```text
AI detects handover need
    ↓
AI explains handover
    ↓
Conversation summary prepared
    ↓
Customer chooses:
    ├── WhatsApp
    ├── Phone
    └── Email
```

## 8. Back Navigation
Every scene must provide a clear way back.

Examples:
- Back to Reception
- Back to Lobby
- Close Panel

The app should preserve:
- conversation history
- selected unit
- reservation progress
- language

## 9. Unknown Question Flow

```text
Customer asks something unclear
    ↓
AI asks one short clarification
    ↓
If still unclear:
show suggested actions
    ↓
If unresolved:
offer staff handover
```

## 10. API Error Flow
If AI fails:

- keep current scene
- keep user input if possible
- show short error
- show Retry
- keep staff contact available

## 11. No Matching Unit
If no unit matches:

- AI explains no exact match was found
- suggests alternatives
- offers staff handover

## 12. Returning Session
If the session is still active:
- do not restart the full opening experience unless user chooses to
- restore current scene
- restore selected unit
- restore reservation state
- restore conversation
