# Database Schema (ERD)

## 1. Users & Authentication

- **users**
  - id (PK)
  - name
  - email (Unique)
  - password
  - role (ENUM: admin, doctor_general, doctor_dental, staff_registration, staff_nurse, staff_pharmacy, patient)
  - phone
  - created_at, updated_at

## 2. Master Data

- **doctors**
  - id (PK)
  - user_id (FK -> users.id)
  - specialization (General/Dental)
  - sip_number (License)
  - is_active
- **medicines**
  - id (PK)
  - name
  - sku
  - stock
  - unit (tablet, bottle, etc.)
  - price
  - description

- **services** (Polyclinic Services)
  - id (PK)
  - name
  - price
  - type (General/Dental)

## 3. Patient Data

- **patients**
  - id (PK)
  - nik (Unique, Optional)
  - name
  - birth_place
  - birth_date
  - gender (M/F)
  - address
  - phone
  - email (Optional)
  - created_at, updated_at

## 4. Transactional Data

- **registrations**
  - id (PK)
  - patient_id (FK -> patients.id)
  - doctor_id (FK -> doctors.id)
  - queue_number (e.g., A-001)
  - status (ENUM: pending, vital_check, consultation, pharmacy, completed, cancelled)
  - complaint
  - created_at (Date of visit)

- **vital_signs**
  - id (PK)
  - registration_id (FK -> registrations.id)
  - systolic
  - diastolic
  - temperature
  - pulse
  - respiratory_rate
  - height
  - weight
  - notes

- **medical_records**
  - id (PK)
  - registration_id (FK -> registrations.id)
  - doctor_id (FK -> doctors.id)
  - diagnosis
  - action_taken
  - notes
  - created_at

- **prescriptions**
  - id (PK)
  - medical_record_id (FK -> medical_records.id)
  - status (ENUM: pending, preparing, ready, delivered)
  - notes

- **prescription_items**
  - id (PK)
  - prescription_id (FK -> prescriptions.id)
  - medicine_id (FK -> medicines.id)
  - quantity
  - dosage (e.g., "3x1 after meal")
  - price_at_moment

- **transactions** (Billing)
  - id (PK)
  - registration_id (FK -> registrations.id)
  - total_amount
  - status (paid, unpaid)
  - payment_method
