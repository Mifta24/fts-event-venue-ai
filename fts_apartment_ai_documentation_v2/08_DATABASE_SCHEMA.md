# FTS APARTMENT AI — Database Schema

## 1. Core Tables

### apartments
- id
- name
- slug
- description
- address
- city
- country
- phone
- whatsapp
- email
- check_in_time
- check_out_time
- default_language
- active
- created_at
- updated_at

### units
- id
- apartment_id
- code
- name
- description
- size_sqm
- bed_type
- max_adults
- max_children
- view_type
- smoking_allowed
- bedrooms (0 = studio)
- bathrooms
- floor_range
- breakfast_included
- price_min
- price_max
- currency
- active
- created_at
- updated_at

### unit_images
- id
- unit_id
- image_url
- alt_text
- sort_order
- active

### unit_amenities
- id
- unit_id
- name
- description
- icon
- sort_order

### facilities
- id
- apartment_id
- name
- slug
- description
- location
- opening_time
- closing_time
- fee_text
- image_url
- active

### apartment_policies
- id
- apartment_id
- policy_type
- title
- content
- language
- active
- effective_from

### faqs
- id
- apartment_id
- category
- question
- answer
- language
- sort_order
- active

### knowledge_documents
- id
- apartment_id
- title
- category
- language
- content
- source_type
- status
- version
- approved_by
- approved_at

### conversations
- id
- apartment_id
- session_id
- language
- current_scene
- selected_unit_id
- status
- started_at
- ended_at

### messages
- id
- conversation_id
- role
- content
- intent
- model
- latency_ms
- metadata
- created_at

### reservation_requests
- id
- reference
- apartment_id
- conversation_id
- unit_id
- check_in
- check_out
- adults
- children
- unit_count
- guest_name
- contact_type
- contact_value
- special_request
- status
- submitted_at

### handovers
- id
- conversation_id
- reservation_request_id
- channel
- reason
- summary
- status
- staff_user_id
- created_at

## 2. Scene State
Store scene-related state in `conversations` or `sessions`.

Recommended:
- current_scene
- selected_unit_id
- selected_facility_id
- reservation_state
- language

## 3. Optional Session Table
If needed:

### visitor_sessions
- id
- apartment_id
- session_uuid
- current_scene
- language
- selected_unit_id
- reservation_state_json
- expires_at
- created_at
- updated_at

## 4. Admin Users
Use existing users table or:

- id
- apartment_id
- name
- email
- password_hash
- role
- active

## 5. Future Tables
- unit_inventory
- unit_rates
- bookings
- payments
- integrations
- promotions
- analytics_events
- guest_profiles
