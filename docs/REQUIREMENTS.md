# Requirements Document - Klinik Pratama Kamulyan

## 1. Stakeholders

- **Admin**: Full system control.
- **Dokter Umum**: General examination, prescriptions.
- **Dokter Gigi**: Dental examination, prescriptions.
- **Petugas Pendaftaran**: Offline registration, patient data entry.
- **Petugas Ruang Tunggu**: Vital signs check.
- **Petugas Farmasi**: Medicine management, prescription processing.
- **Pasien**: Online registration, clinic info.

## 2. Modules & Features

### A. Public Website (Company Profile)

- Homepage (Clinic Info, History)
- Service List (General & Dental)
- Doctor Profile & Schedule
- Contact & Location
- Testimonials & Articles

### B. Patient Registration Module

- **Online**: Form (Name, DOB, Gender, Address, Phone, Doctor, Complaint, Optional KTP).
- **Offline**: Same form, input by staff, auto-generate queue number + printed slip.

### C. Queue Management Module

- **Queues**: Registration -> Waiting Room (Vitals) -> Doctor -> Pharmacy.
- **Features**: Real-time display, Call system, Transfer logic.

### D. Medical Examination Module (Doctor)

- **Dashboard**: Pending patients, History.
- **Action**: Diagnosis, Treatment, Prescription, Notes, Follow-up.
- **Vitals**: Input by nurse (BP, Temp, Pulse, Breath, Height/Weight).

### E. Pharmacy Module

- Incoming Prescriptions.
- Stock Management.
- Status Updates (Preparation -> Ready -> Done).

### F. Admin Management Module

- **User Management**: Roles & Access.
- **Doctor Management**: Schedule, Specialization.
- **Master Data**: Medicines, Services, Tariffs.
- **Reporting**: Daily/Monthly reports, Patient stats, Revenue.

## 3. Tech Stack

- **Backend**: Laravel (PHP)
- **Frontend**: Inertia.js + Vue 3 + Tailwind CSS
- **Database**: MySQL 8.0
- **Real-time**: Laravel Reverb / Pusher
- **Server**: Nginx / Ubuntu
